<?php

namespace Ovesco\FacturationBundle\ListModel\Column;

use NetBS\ListBundle\Column\BaseColumn;
use Ovesco\FacturationBundle\Entity\Creance;

class FactureCreancesColumn extends BaseColumn
{
    /**
     * Return content related to the given object with the given params
     * @param Creance[] $creances
     * @param array $params
     * @return string
     */
    public function getContent($creances, array $params = [])
    {
        $texte = "";
        foreach($creances as $creance)
            $texte .= $creance->getTitre() . "<br/>";
        return $texte;
    }
}
