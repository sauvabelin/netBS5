<?php

namespace NetBS\CoreBundle\ListModel\Column;

use NetBS\ListBundle\Column\BaseColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

class RemoveFromDynamicColumn extends BaseColumn
{
    protected $router;

    public function __construct(RouterInterface $router)
    {
        $this->router   = $router;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setRequired('listId')
            ->setDefault(BaseColumn::SORTABLE, false);
    }

    /**
     * Return content related to the given object with the given params
     * @param object $item
     * @param array $params
     * @return string
     */
    public function getContent($item, array $params = [])
    {
        $data = json_encode(["removed_ids" => [$item->getId()]]);
        $path = $this->router->generate('netbs.core.dynamics_list.remove_items', array('id' => $params['listId'])) . "?data=$data";
        return "<a href='$path' class='btn btn-xs btn-danger' data-toggle='tooltip' title='Retirer de la liste'><i class='fas fa-sm fa-times'></i></a>";
    }
}
