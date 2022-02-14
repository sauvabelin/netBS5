<?php

namespace NetBS\SecureBundle\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Service\SecureConfig;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinTable;

class DoctrineMapperSubscriber implements EventSubscriber
{
    protected $fichierConfig;

    protected $secureConfig;

    public function __construct(FichierConfig $fichierConfig, SecureConfig $secureConfig)
    {
        $this->fichierConfig    = $fichierConfig;
        $this->secureConfig     = $secureConfig;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::loadClassMetadata
        ];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs) {

        switch($eventArgs->getClassMetadata()->getName()) {

            case $this->secureConfig->getRoleClass():
                $this->mapRole($eventArgs);
                break;

            case $this->secureConfig->getUserClass():
                $this->mapUser($eventArgs);
                break;
            case $this->secureConfig->getAutorisationClass():
                $this->mapAutorisation($eventArgs);
            default:
                return;
        }
    }

    protected function mapAutorisation(LoadClassMetadataEventArgs $eventArgs) {
        $eventArgs->getClassMetadata()->mapManyToOne([
            'fieldName'     => 'user',
            'inversedBy'    => 'autorisations',
            'targetEntity'  => $this->secureConfig->getUserClass()
        ]);

        $eventArgs->getClassMetadata()->mapManyToOne([
            'fieldName'     => 'groupe',
            'targetEntity'  => $this->fichierConfig->getGroupeClass()
        ]);

        $eventArgs->getClassMetadata()->mapManyToMany([
            'fieldName'     => 'roles',
            'targetEntity'  => $this->secureConfig->getRoleClass()
        ]);
    }

    protected function mapUser(LoadClassMetadataEventArgs $eventArgs) {

        $eventArgs->getClassMetadata()->mapOneToOne([
            'fieldName'     => 'membre',
            'fetch'         => 'EAGER',
            'targetEntity'  => $this->fichierConfig->getMembreClass()
        ]);

        $eventArgs->getClassMetadata()->mapManyToMany([
            'fieldName'     => 'roles',
            'fetch'         => 'EAGER',
            'targetEntity'  => $this->secureConfig->getRoleClass()
        ]);

        $eventArgs->getClassMetadata()->table['uniqueConstraints'][] = [
            'name'      => 'unique_target_member',
            'columns'   => ['membre_id']
        ];

        $eventArgs->getClassMetadata()->mapOneToMany([
            'fieldName'     => 'autorisations',
            'targetEntity'  => $this->secureConfig->getAutorisationClass(),
            'mappedBy'      => 'user',
            'cascade'       => ['remove']
        ]);
    }

    protected function mapRole(LoadClassMetadataEventArgs $eventArgs) {

        $eventArgs->getClassMetadata()->mapOneToMany([
            'fieldName'     => 'children',
            'targetEntity'  => $this->secureConfig->getRoleClass(),
            'mappedBy'      => 'parent'
        ]);

        $eventArgs->getClassMetadata()->mapManyToOne([
            'fieldName'     => 'parent',
            'targetEntity'  => $this->secureConfig->getRoleClass(),
            'inversedBy'    => 'children'
        ]);
    }
}
