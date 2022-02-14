<?php

namespace NetBS\CoreBundle\ListModel\Column;

use NetBS\CoreBundle\ListModel\Action\ActionInterface;
use NetBS\CoreBundle\ListModel\ActionItem;
use NetBS\CoreBundle\Service\ListActionsManager;
use NetBS\ListBundle\Column\BaseColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActionColumn extends BaseColumn
{
    const ACTIONS_KEY   = 'actions';

    private $manager;

    public function __construct(ListActionsManager $manager)
    {
        $this->manager  = $manager;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefault(self::ACTIONS_KEY, [])
            ->setDefault(BaseColumn::SORTABLE, false);
    }

    /**
     * Return content related to the given object with the given params
     * @param object $item
     * @param array $params
     * @return string
     * @throws \Exception
     */
    public function getContent($item, array $params = [])
    {
        $html       = '';

        foreach($params[self::ACTIONS_KEY] as $key  => $value) {

            $class      = null;
            $params     = null;
            if($value instanceof ActionItem) {
                $class  = $value->getActionClass();
                $params = $value->getActionParams();
            }

            else {
                $class = is_array($value) ? $key : $value;
                $params = is_array($value) ? $value : [];
            }

            $action     = $this->manager->getAction($class);
            $options    = new OptionsResolver();

            $action->configureOptions($options);
            $data       = $options->resolve($params);

            $html  .= $action->render($item, $data) . " ";
        };

        return $html;
    }
}