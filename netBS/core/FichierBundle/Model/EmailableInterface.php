<?php

namespace NetBS\FichierBundle\Model;

interface EmailableInterface
{
    public function getSendableEmail();
}