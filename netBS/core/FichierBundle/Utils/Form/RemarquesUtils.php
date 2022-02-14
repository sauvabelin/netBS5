<?php

namespace NetBS\FichierBundle\Utils\Form;

use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class RemarquesUtils
{
    public static function addRemarquesField(FormBuilderInterface $builder) {

        $builder->add('remarques', TextareaType::class, array(
            'label'     => 'Remarques',
            'required'  => false
        ));
    }
}