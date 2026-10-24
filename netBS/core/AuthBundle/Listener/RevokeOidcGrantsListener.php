<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Listener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use NetBS\AuthBundle\Contract\Subject;
use NetBS\AuthBundle\Service\HydraAdminClient;
use NetBS\AuthBundle\Service\HydraClientException;
use NetBS\SecureBundle\Mapping\BaseUser;
use Psr\Log\LoggerInterface;

/**
 * When a user's password changes or their account is deactivated, revoke
 * everything Hydra issued to them: consent sessions (which also revokes their
 * access and refresh tokens) and SSO login sessions. Otherwise a stolen RP
 * session would outlive a password reset by the refresh-token lifetime.
 *
 * Hooks the entity rather than each controller, so every path (self-service
 * reset, admin change, inline isActive toggle) is covered. Hydra is called
 * after the flush, so a DB rollback never revokes anything; a Hydra failure
 * is logged and never fails the save.
 */
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
final class RevokeOidcGrantsListener
{
    /** @var array<string, string> subject => reason */
    private array $pending = [];

    public function __construct(
        private readonly HydraAdminClient $hydra,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $user = $args->getObject();
        if (!$user instanceof BaseUser || $user->getUsername() === null) {
            return;
        }

        // passwordChangedAt, not password: every real change sets it, while a
        // transparent hash upgrade at login only touches password.
        if ($args->hasChangedField('passwordChangedAt')) {
            $this->pending[Subject::fromUsername($user->getUsername())] = 'password_changed';
        } elseif ($args->hasChangedField('isActive') && $args->getOldValue('isActive') && !$args->getNewValue('isActive')) {
            $this->pending[Subject::fromUsername($user->getUsername())] = 'deactivated';
        }
    }

    public function postFlush(): void
    {
        $pending = $this->pending;
        $this->pending = [];

        foreach ($pending as $subject => $reason) {
            try {
                $this->hydra->revokeAllSessionsForSubject($subject);
                $this->hydra->revokeLoginSessionsForSubject($subject);
                $this->logger->info('oidc.grants_revoked', ['subject' => $subject, 'reason' => $reason]);
            } catch (HydraClientException $e) {
                $this->logger->error('oidc.grants_revoke_failed', [
                    'subject' => $subject,
                    'reason' => $reason,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
