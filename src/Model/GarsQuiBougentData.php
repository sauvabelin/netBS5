<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class GarsQuiBougentData
{
    /**
     * @var string
     * @Assert\NotBlank()
     */
    private $sexe;

    /**
     * @var string
     * @Assert\NotBlank()
     */
    private $age;

    /**
     * @return string
     */
    public function getSexe()
    {
        return $this->sexe;
    }

    /**
     * @param string $sexe
     */
    public function setSexe($sexe)
    {
        $this->sexe = $sexe;
    }

    /**
     * @return string
     */
    public function getAge()
    {
        return $this->age;
    }

    /**
     * @param string $age
     */
    public function setAge($age)
    {
        $this->age = $age;
    }
}
