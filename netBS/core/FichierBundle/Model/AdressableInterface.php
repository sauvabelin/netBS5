<?php

namespace NetBS\FichierBundle\Model;

interface AdressableInterface
{
    /**
     * @return mixed
     */
    public function getId();

    /**
     * @return string
     */
    public function __toString();

    /**
     * @return OwnableAdresse
     */
    public function getSendableAdresse();
}