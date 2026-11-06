<?php

namespace App\Tests\Support;

use App\Entity\BSGroupe;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\FichierBundle\Entity\Fonction;
use NetBS\FichierBundle\Entity\GroupeCategorie;
use NetBS\FichierBundle\Entity\GroupeType;

/**
 * Creates minimal Fichier rows (catégorie → type → groupe) in netbs5_test for settings
 * tests, and removes them again — bypassing soft-delete — in removeFichierRows().
 */
trait FichierRowsTrait
{
    private array $fichierRows = [];

    protected function createGroupeType(EntityManagerInterface $em, string $nom = 'settings-test type'): GroupeType
    {
        $categorie = new GroupeCategorie('settings-test catégorie');
        $type      = new GroupeType();
        $type->setNom($nom);
        $type->setGroupeCategorie($categorie);

        $em->persist($categorie);
        $em->persist($type);
        $em->flush();

        array_unshift($this->fichierRows, $categorie);
        array_unshift($this->fichierRows, $type);

        return $type;
    }

    protected function createGroupe(EntityManagerInterface $em, string $nom = 'settings-test groupe'): BSGroupe
    {
        $groupe = new BSGroupe();
        $groupe->setNom($nom);
        $groupe->setGroupeType($this->createGroupeType($em));

        $em->persist($groupe);
        $em->flush();

        array_unshift($this->fichierRows, $groupe);

        return $groupe;
    }

    protected function createFonction(EntityManagerInterface $em, string $nom = 'settings-test fonction'): Fonction
    {
        $fonction = new Fonction();
        $fonction->setNom($nom)->setPoids(10)->setAbbreviation('STF');

        $em->persist($fonction);
        $em->flush();

        array_unshift($this->fichierRows, $fonction);

        return $fonction;
    }

    protected function removeFichierRows(EntityManagerInterface $em): void
    {
        $em->getFilters()->disable('softdeleteable');
        foreach ($this->fichierRows as $row) {
            $em->createQuery(sprintf('DELETE FROM %s r WHERE r.id = :id', get_class($row)))
                ->setParameter('id', $row->getId())->execute();
        }
        $em->getFilters()->enable('softdeleteable');
        $this->fichierRows = [];
    }
}
