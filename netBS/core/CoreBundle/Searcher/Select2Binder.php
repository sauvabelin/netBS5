<?php

namespace NetBS\CoreBundle\Searcher;

use Doctrine\ORM\QueryBuilder;
use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\CoreBundle\Model\BaseBinder;
use Symfony\Component\Form\Form;

class Select2Binder extends BaseBinder
{
    protected $count = 0;

    public function getType()
    {
        return AjaxSelect2DocumentType::class;
    }

    public function bind($alias, Form $form, QueryBuilder $builder)
    {
        $multiple       = $form->getConfig()->getOption('multiple');
        $data           = $form->getData();
        $field          = $alias . "." . $form->getName();
        $param          = $this->getParamName();

        if(!$multiple) {

            if(!$data)
                return;

            $builder->andWhere($builder->expr()->eq($field, ':' . $param))
                ->setParameter($param, $data);
        }

        else {

            if(!is_array($data) || empty($data))
                return;

            $builder->andWhere($builder->expr()->in($field, ':' . $param))
                ->setParameter($param, $data);
        }
    }

    protected function getParamName() {

        return 'ajax_select2_param_' . $this->count++;
    }
}
