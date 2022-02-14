<?php

namespace NetBS\CoreBundle\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use NetBS\CoreBundle\Entity\DynamicList;
use NetBS\CoreBundle\Entity\ExportConfiguration;
use NetBS\CoreBundle\Entity\LoggedChange;
use NetBS\CoreBundle\Entity\News;
use NetBS\CoreBundle\Entity\Notification;
use NetBS\CoreBundle\Entity\UserLog;
use NetBS\SecureBundle\Service\SecureConfig;

class DoctrineMapperSubscriber implements EventSubscriber
{
    protected $secureConfig;

    public function __construct(SecureConfig $secureConfig)
    {
        $this->secureConfig = $secureConfig;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::loadClassMetadata
        ];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs) {

        switch($eventArgs->getClassMetadata()->getName()) {

            case DynamicList::class:
                $eventArgs->getClassMetadata()->mapManyToOne([
                    'fieldName'     => 'owner',
                    'targetEntity'  => $this->secureConfig->getUserClass()
                ]);
                break;

            case ExportConfiguration::class:
                $eventArgs->getClassMetadata()->mapManyToOne([
                    'fieldName'     => 'user',
                    'targetEntity'  => $this->secureConfig->getUserClass()
                ]);
                break;
            case LoggedChange::class:
                $eventArgs->getClassMetadata()->mapManyToOne([
                    'fieldName'     => 'user',
                    'targetEntity'  => $this->secureConfig->getUserClass(),
                    'fetch'         => 'EAGER'
                ]);
                break;
            case Notification::class:
                $eventArgs->getClassMetadata()->mapManyToOne([
                    'fieldName'     => 'user',
                    'targetEntity'  => $this->secureConfig->getUserClass(),
                    'fetch'         => 'EAGER'
                ]);
                break;
            case UserLog::class:
                $eventArgs->getClassMetadata()->mapManyToOne([
                    'fieldName'     => 'user',
                    'targetEntity'  => $this->secureConfig->getUserClass(),
                    'fetch'         => 'EAGER'
                ]);
                break;
            case News::class:
                $eventArgs->getClassMetadata()->mapManyToOne([
                    'fieldName'     => 'user',
                    'targetEntity'  => $this->secureConfig->getUserClass(),
                    'fetch'         => 'EAGER'
                ]);
                break;
            default:
                return;
        }
    }
}
