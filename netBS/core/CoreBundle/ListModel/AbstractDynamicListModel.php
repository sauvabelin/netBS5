<?php

namespace NetBS\CoreBundle\ListModel;

use NetBS\ListBundle\Model\BaseListModel;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractDynamicListModel extends BaseListModel
{
    use EntityManagerTrait;

    const LIST_ID = 'listId';

    abstract public function getManagedName();

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(self::LIST_ID);
    }

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->getRepository('NetBSCoreBundle:DynamicList')
            ->find($this->getParameter(self::LIST_ID))->getItems();
    }
}