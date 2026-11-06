<?php

namespace App\Tests\Support;

use App\Entity\BSGroupe;
use App\Entity\BSMembre;
use NetBS\FichierBundle\Entity\Attribution;
use NetBS\FichierBundle\Entity\Fonction;
use NetBS\FichierBundle\Entity\GroupeType;

/** Builds unsaved Fichier entities with ids set by reflection, for rule block tests. */
trait InMemoryFichierTrait
{
    protected function groupe(int $id, ?GroupeType $type = null, ?BSGroupe $parent = null): BSGroupe
    {
        $groupe = new BSGroupe();
        $this->setEntityId($groupe, $id);
        if ($type) {
            $groupe->setGroupeType($type);
        }
        $parent?->addEnfant($groupe);

        return $groupe;
    }

    protected function groupeType(int $id): GroupeType
    {
        $type = new GroupeType();
        $this->setEntityId($type, $id);

        return $type;
    }

    protected function fonction(int $id, int $poids = 0): Fonction
    {
        $fonction = new Fonction();
        $this->setEntityId($fonction, $id);
        $fonction->setPoids($poids);

        return $fonction;
    }

    protected function membre(int $id): BSMembre
    {
        $membre = new BSMembre();
        $this->setEntityId($membre, $id);

        return $membre;
    }

    protected function attribute(BSMembre $membre, BSGroupe $groupe, Fonction $fonction, bool $active = true): Attribution
    {
        $attribution = new Attribution();
        $attribution->setFonction($fonction);
        $attribution->setDateDebut(new \DateTime('-10 days'));
        if (!$active) {
            $attribution->setDateFin(new \DateTime('-1 day'));
        }
        $membre->addAttribution($attribution);
        $groupe->addAttribution($attribution);

        return $attribution;
    }

    protected function setEntityId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
