<?php

namespace NetBS\FichierBundle\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Service\SecureConfig;

class DoctrineMapperSubscriber implements EventSubscriber
{
    protected $config;

    protected $secureConfig;

    public function __construct(FichierConfig $config, SecureConfig $secureConfig)
    {
        $this->config       = $config;
        $this->secureConfig = $secureConfig;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::loadClassMetadata,
            Events::prePersist
        ];
    }

    public function prePersist(LifecycleEventArgs $eventArgs) {

        switch(ClassUtils::getClass($eventArgs->getObject()))
        {
            case $this->config->getContactInformationClass():
                $eventArgs->getObject()->_linkItems();
        }
    }
    
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs) {


        switch($eventArgs->getClassMetadata()->getName())
        {
            case $this->config->getMembreClass():
                $this->configureMembreMapping($eventArgs);
                break;

            case $this->config->getObtentionDistinctionClass():
                $this->configureObtentionDistinctionMapping($eventArgs);
                break;

            case $this->config->getAttributionClass():
                $this->configureAttributionMapping($eventArgs);
                break;

            case $this->config->getFamilleClass():
                $this->configureFamilleMapping($eventArgs);
                break;

            case $this->config->getGeniteurClass():
                $this->configureGeniteurMapping($eventArgs);
                break;

            case $this->config->getGroupeTypeClass():
                $this->configureGroupeType($eventArgs);
                break;

            case $this->config->getGroupeClass():
                $this->configureGroupeMapping($eventArgs);
                break;
            case $this->config->getFonctionClass():
                $this->configureFonctionMapping($eventArgs);
                break;
            case $this->config->getAdresseClass():
                $this->configureAdresseMapping($eventArgs);
                break;
            case $this->config->getTelephoneClass():
                $this->configureTelephoneMapping($eventArgs);
                break;
            case $this->config->getEmailClass():
                $this->configureEmailMapping($eventArgs);
                break;
            case $this->config->getContactInformationClass():
                $this->configureContactInformationMapping($eventArgs);
                break;
        }
    }

    protected function configureMembreMapping(LoadClassMetadataEventArgs $args) {

        $metadata       = $args->getClassMetadata();

        //Famille
        $metadata->mapManyToOne([
            'fieldName'     => 'famille',
            'targetEntity'  =>  $this->config->getFamilleClass(),
            'inversedBy'    => 'membres',
            'cascade'       => ['persist']
        ]);

        //Attributions
        $metadata->mapOneToMany([
            'fieldName'     => 'attributions',
            'targetEntity'  =>  $this->config->getAttributionClass(),
            'mappedBy'      => 'membre',
            'cascade'       => ['persist', 'remove']
        ]);

        //OD
        $metadata->mapOneToMany([
            'fieldName'     => 'obtentionsDistinction',
            'targetEntity'  =>  $this->config->getObtentionDistinctionClass(),
            'mappedBy'      => 'membre',
            'cascade'       => ['persist', 'remove']
        ]);

        $metadata->mapOneToOne([
            'fieldName'     => 'contactInformation',
            'targetEntity'  => $this->config->getContactInformationClass(),
            'cascade'       => ['persist', 'remove'],
            'fetch'         => 'EAGER'
        ]);

        $metadata->table['indexes'][] = [
            'columns'   => ['prenom', 'nom'],
            'flags'     => ['fulltext']
        ];
    }

    protected function configureObtentionDistinctionMapping(LoadClassMetadataEventArgs $args) {

        $metadata       = $args->getClassMetadata();

        $metadata->mapManyToOne([
            'fieldName'     => 'distinction',
            'targetEntity'  => $this->config->getDistinctionClass(),
        ]);

        $metadata->mapManyToOne([
            'fieldName'     => 'membre',
            'targetEntity'  => $this->config->getMembreClass(),
            'inversedBy'    => 'obtentionsDistinction'
        ]);
    }

    protected function configureAttributionMapping(LoadClassMetadataEventArgs $args) {

        $metadata       = $args->getClassMetadata();

        $metadata->mapManyToOne([
            'fieldName'     => 'membre',
            'targetEntity'  => $this->config->getMembreClass(),
            'inversedBy'    => 'attributions',
            'fetch'         => 'EAGER'
        ]);

        $metadata->mapManyToOne([
            'fieldName'     => 'groupe',
            'targetEntity'  => $this->config->getGroupeClass(),
            'inversedBy'    => 'attributions',
        ]);

        $metadata->mapManyToOne([
            'fieldName'     => 'fonction',
            'targetEntity'  => $this->config->getFonctionClass(),
        ]);
    }

    protected function configureFamilleMapping(LoadClassMetadataEventArgs $args) {

        $metadata       = $args->getClassMetadata();

        $metadata->mapOneToMany([
            'fieldName'     => 'membres',
            'targetEntity'  => $this->config->getMembreClass(),
            'mappedBy'      => 'famille',
            'cascade'       => ['persist', 'remove']
        ]);

        $metadata->mapOneToMany([
            'fieldName'     => 'geniteurs',
            'targetEntity'  => $this->config->getGeniteurClass(),
            'mappedBy'      => 'famille',
            'cascade'       => ['persist', 'remove']
        ]);

        $metadata->mapOneToOne([
            'fieldName'     => 'contactInformation',
            'targetEntity'  => $this->config->getContactInformationClass(),
            'cascade'       => ['persist', 'remove'],
            'fetch'         => 'EAGER'
        ]);
    }

    protected function configureGeniteurMapping(LoadClassMetadataEventArgs $args) {

        $args->getClassMetadata()->mapManyToOne([
            'fieldName'     => 'famille',
            'inversedBy'    => 'geniteurs',
            'targetEntity'  => $this->config->getFamilleClass()
        ]);

        $args->getClassMetadata()->mapOneToOne([
            'fieldName'     => 'contactInformation',
            'targetEntity'  => $this->config->getContactInformationClass(),
            'cascade'       => ['persist', 'remove'],
            'fetch'         => 'EAGER'
        ]);
    }

    protected function configureGroupeType(LoadClassMetadataEventArgs $args) {

        $args->getClassMetadata()->mapManyToOne([
            'fieldName'     => 'groupeCategorie',
            'targetEntity'  => $this->config->getGroupeCategorieClass()
        ]);
    }

    protected function configureGroupeMapping(LoadClassMetadataEventArgs $args) {

        $metadata   = $args->getClassMetadata();

        $metadata->mapManyToOne([
            'fieldName'     => 'parent',
            'targetEntity'  => $this->config->getGroupeClass(),
            'inversedBy'    => 'enfants'
        ]);

        $metadata->mapOneToMany([
            'fieldName'     => 'enfants',
            'targetEntity'  => $this->config->getGroupeClass(),
            'mappedBy'      => 'parent'
        ]);

        $metadata->mapManyToOne([
            'fieldName'     => 'groupeType',
            'targetEntity'  => $this->config->getGroupeTypeClass()
        ]);

        $metadata->mapOneToMany([
            'fieldName'     => 'attributions',
            'targetEntity'  => $this->config->getAttributionClass(),
            'mappedBy'      => 'groupe'
        ]);
    }

    protected function configureFonctionMapping(LoadClassMetadataEventArgs $eventArgs) {

        $eventArgs->getClassMetadata()->mapManyToMany([
            'fieldName'     => 'roles',
            'targetEntity'  => $this->secureConfig->getRoleClass()
        ]);
    }

    protected function configureAdresseMapping(LoadClassMetadataEventArgs $eventArgs)
    {
        $eventArgs->getClassMetadata()->mapManyToOne([
            'fieldName'     => 'contactInformation',
            'targetEntity'  => $this->config->getContactInformationClass(),
            'inversedBy'    => 'adresses'
        ]);
    }

    protected function configureTelephoneMapping(LoadClassMetadataEventArgs $eventArgs)
    {
        $eventArgs->getClassMetadata()->mapManyToOne([
            'fieldName'     => 'contactInformation',
            'targetEntity'  => $this->config->getContactInformationClass(),
            'inversedBy'    => 'telephones'
        ]);
    }

    protected function configureEmailMapping(LoadClassMetadataEventArgs $eventArgs)
    {
        $eventArgs->getClassMetadata()->mapManyToOne([
            'fieldName'     => 'contactInformation',
            'targetEntity'  => $this->config->getContactInformationClass(),
            'inversedBy'    => 'emails'
        ]);
    }

    protected function configureContactInformationMapping(LoadClassMetadataEventArgs $eventArgs)
    {
        $eventArgs->getClassMetadata()->mapOneToMany([
            'fieldName'     => 'adresses',
            'targetEntity'  => $this->config->getAdresseClass(),
            'mappedBy'      => 'contactInformation',
            'cascade'       => ['persist', 'remove']
        ]);

        $eventArgs->getClassMetadata()->mapOneToMany([
            'fieldName'     => 'telephones',
            'targetEntity'  => $this->config->getTelephoneClass(),
            'mappedBy'      => 'contactInformation',
            'cascade'       => ['persist', 'remove']
        ]);

        $eventArgs->getClassMetadata()->mapOneToMany([
            'fieldName'     => 'emails',
            'targetEntity'  => $this->config->getEmailClass(),
            'mappedBy'      => 'contactInformation',
            'cascade'       => ['persist', 'remove']
        ]);
    }
}