<?php

namespace NetBS\CoreBundle\Model;

use Symfony\Component\Form\FormBuilderInterface;

interface ConfigurableAutomaticInterface
{
    /**
     * @param FormBuilderInterface $builder
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder);

    /**
     * Returns something that will be injected in the form
     * builder, and available in your automatic
     * @return mixed
     */
    public function buildDataHolder();
}