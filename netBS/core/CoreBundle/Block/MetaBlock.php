<?php

namespace NetBS\CoreBundle\Block;

use Symfony\Component\OptionsResolver\OptionsResolver;

class MetaBlock
{
    /**
     * @var string
     */
    protected $class;

    /**
     * @var ParamBag
     */
    protected $parameters;

    /**
     * @var string
     */
    protected $content;

    public function __construct($class, array $params = [])
    {
        $this->class        = $class;
        $this->parameters   = new ParamBag($params);
    }

    public function __toString()
    {
        return $this->content;
    }

    public function validate(OptionsResolver $resolver) {

        return $resolver->resolve($this->parameters->getParams());
    }

    public function setContent($content) {

        $this->content  = $content;
    }

    /**
     * @return ParamBag
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @return string
     */
    public function getBlockClass()
    {
        return $this->class;
    }
}