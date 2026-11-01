<?php

namespace NetBS\CoreBundle\Settings;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use NetBS\CoreBundle\Entity\Parameter;
use NetBS\CoreBundle\Exceptions\UserConstraintException;
use NetBS\CoreBundle\Service\ParameterManager;

/**
 * Validates declared settings on every write path (Paramètres page, XEditable endpoint,
 * fixtures, ad-hoc code) and keeps the ParameterManager cache in sync with the table.
 */
#[AsDoctrineListener(event: Events::postFlush)]
#[AsEntityListener(event: Events::prePersist, method: 'validate', entity: Parameter::class, lazy: true)]
#[AsEntityListener(event: Events::preUpdate, method: 'validate', entity: Parameter::class, lazy: true)]
#[AsEntityListener(event: Events::postPersist, method: 'refresh', entity: Parameter::class, lazy: true)]
#[AsEntityListener(event: Events::postUpdate, method: 'refresh', entity: Parameter::class, lazy: true)]
#[AsEntityListener(event: Events::postRemove, method: 'forget', entity: Parameter::class, lazy: true)]
class ParameterWriteListener
{
    public function __construct(
        private SettingsRegistry $registry,
        private SettingValidator $validator,
        private ParameterManager $parameters,
    ) {
    }

    public function validate(Parameter $parameter): void
    {
        $definition = $this->registry->get($parameter->getNamespace(), $parameter->getKey());

        if ($definition && ($error = $this->validator->validate($definition, $parameter->getValue())) !== null) {
            throw new UserConstraintException($error);
        }
    }

    public function refresh(Parameter $parameter): void
    {
        $this->parameters->refreshCache($parameter);
    }

    public function forget(Parameter $parameter): void
    {
        $this->parameters->forgetCache($parameter);
    }

    public function postFlush(): void
    {
        $this->parameters->forgetResolvedReferences();
    }
}
