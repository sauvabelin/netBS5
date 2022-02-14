<?php

namespace NetBS\CoreBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\DynamicList;
use NetBS\CoreBundle\ListModel\AbstractDynamicListModel;
use NetBS\ListBundle\Exceptions\ListModelNotFoundException;
use NetBS\ListBundle\Model\ListModelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DynamicListManager
{
    /**
     * @var AbstractDynamicListModel[]
     */
    protected $listModels   = [];

    /**
     * @var EntityManagerInterface
     */
    protected $manager;

    /**
     * @var ListBridgeManager
     */
    protected $bridges;

    /**
     * @var TokenStorageInterface
     */
    protected $token;

    public function __construct(EntityManagerInterface $manager, TokenStorageInterface $storage, ListBridgeManager $bridgeManager)
    {
        $this->manager              = $manager;
        $this->token                = $storage->getToken();
        $this->bridges              = $bridgeManager;
    }

    /**
     * @param AbstractDynamicListModel $listModel
     */
    public function registerModel(AbstractDynamicListModel $listModel) {
        $this->listModels[] = $listModel;
    }

    /**
     * @return AbstractDynamicListModel[]
     */
    public function getListModels() {
        return $this->listModels;
    }

    /**
     * @return array
     */
    public function getManagedClasses() {

        $classes    = [];

        foreach($this->listModels as $model) {
            $classes[$model->getManagedName()] = $model->getManagedItemsClass();
        }

        return $classes;
    }

    /**
     * @return DynamicList[]
     */
    public function getCurrentUserLists() {

        return $this->manager
            ->getRepository('NetBSCoreBundle:DynamicList')
            ->findForUser($this->token->getUser());
    }

    /**
     * @param DynamicList $list
     * @return DynamicList
     */
    public function saveNewList(DynamicList $list) {

        $list->setOwner($this->token->getUser());
        $this->manager->persist($list);
        $this->manager->flush();

        return $list;
    }

    /**
     * @param string $class
     * @return DynamicList[]
     */
    public function getAvailableLists($class) {

        $lists  = [];
        foreach($this->getCurrentUserLists() as $list)
            if($list->getItemsClass() === $class)
                $lists[] = $list;

        foreach($this->getCurrentUserLists() as $list)
            if($this->bridges->isValidTransformation($class, $list->getItemsClass()) && !in_array($list, $lists))
                $lists[] = $list;

        return $lists;
    }

    /**
     * @param $item
     * @param DynamicList $list
     */
    public function addItemToList($item, DynamicList $list) {

        $pushableItem = $this->bridges->convertItem($item, $list->getItemsClass());

        if (!$list->getItems()->contains($pushableItem))
            $list->addItem($pushableItem);
    }

    /**
     * @param $class
     * @return ListModelInterface
     * @throws ListModelNotFoundException
     */
    public function getModelForClass($class) {

        foreach($this->getListModels() as $model)
            if($model->getManagedItemsClass() == $class)
                return $model;

        throw new ListModelNotFoundException($class, "class");
    }

    /**
     * @param DynamicList $list
     * @return string
     */
    public function serialize(DynamicList $list) {

        $ids        = [];
        foreach($list->getItems() as $item)
            $ids[]  = $item->getId();

        $content    = [
            'name'  => base64_encode($list->getName()),
            'class' => base64_encode($list->getItemsClass()),
            'ids'   => implode(',', $ids)
        ];

        return serialize($content);
    }

    public function unserialize($dynamicData) {

        $data       = unserialize($dynamicData);

        if(!isset($data['name']) || !isset($data['class']) || !isset($data['ids']))
            throw new \Exception("Invalid file format.");

        $dn         = new DynamicList();
        $dn->setItemsClass(base64_decode($data['class']))
            ->setOwner($this->token->getUser())
            ->setName("[import] " . base64_decode($data['name']) . "-" . mt_rand(1,9999));

        $items      = $this->manager->createQueryBuilder()
            ->select('x')
            ->from($dn->getItemsClass(), 'x')
            ->where('x.id IN (:ids)')
            ->setParameter('ids', explode(',', $data['ids']))
            ->getQuery()
            ->execute();

        foreach($items as $item)
            $dn->addItem($item);

        try {

            $this->manager->persist($dn);
            $this->manager->flush();

        } catch (\Exception $e) {
            throw new \Exception("Failed saving imported list!");
        }
    }
}
