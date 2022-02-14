<?php

namespace NetBS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use NetBS\SecureBundle\Mapping\BaseUser;

/**
 * DynamicList
 *
 * @ORM\Table(name="netbs_core_dynamic_lists")
 * @ORM\Entity(repositoryClass="NetBS\CoreBundle\Repository\DynamicListRepository")
 */
class DynamicList
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     * @Assert\NotBlank(message="Le nom de la liste ne peut être vide")
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(name="itemClass", type="string", length=255)
     */
    protected $itemsClass;

    /**
     * @var BaseUser
     */
    protected $owner;

    /**
     * @var array
     *
     * @ORM\Column(name="items", type="array")
     */
    protected $itemIds = [];

    /**
     * @var ArrayCollection
     */
    protected $items;

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set owner
     *
     * @param BaseUser $owner
     * @return $this
     */
    public function setOwner(BaseUser $owner)
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * Get owner
     *
     * @return BaseUser $owner
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return DynamicList
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set itemClass
     *
     * @param string $itemClass
     *
     * @return DynamicList
     */
    public function setItemsClass($itemClass)
    {
        $this->itemsClass = $itemClass;

        return $this;
    }

    /**
     * Get itemClass
     *
     * @return string
     */
    public function getItemsClass()
    {
        return $this->itemsClass;
    }

    /**
     * Add item
     *
     * @param $item
     */
    public function addItem($item)
    {
        if(!in_array($item->getId(), $this->itemIds)) {
            $this->itemIds[] = $item->getId();
            $this->items[]  = $item;
        }
    }

    /**
     * Remove item
     *
     * @param $item
     */
    public function removeItem($item)
    {
        foreach($this->itemIds as $k => $id) {
            if($id == $item->getId()) {
                unset($this->itemIds[$k]);
            }
        }

        $this->items->removeElement($item);
    }

    /**
     * Get items
     *
     * @return \Doctrine\Common\Collections\Collection $items
     */
    public function getItems()
    {
        return $this->items;
    }

    public function _setItems(array $actualItems) {

        $this->items = new ArrayCollection($actualItems);
    }

    public function _getItemIds() {

        return $this->itemIds;
    }
}

