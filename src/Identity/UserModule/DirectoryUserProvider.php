<?php

declare(strict_types=1);

namespace App\Identity\UserModule;

use App\Entity\BSUser;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\AuthBundle\Contract\DirectoryUserProviderInterface;
use NetBS\AuthBundle\Contract\Subject;
use NetBS\AuthBundle\Directory\DirectoryProjection;
use NetBS\AuthBundle\Directory\DirectoryUser;

/**
 * Directory users: active accounts with nextcloud_account = 1, with the
 * directory roles they hold (BaseUser::getAllRoles(): direct grants and
 * fonctions of active attributions).
 */
final class DirectoryUserProvider implements DirectoryUserProviderInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function directoryUsers(): array
    {
        /** @var BSUser[] $accounts */
        $accounts = $this->em->createQueryBuilder()
            ->select('u', 'm', 'ur', 'a', 'f', 'fr')
            ->from(BSUser::class, 'u')
            ->leftJoin('u.membre', 'm')
            // Fetched up front so getAllRoles() doesn't lazy-load per user.
            ->leftJoin('u.roles', 'ur')
            ->leftJoin('m.attributions', 'a')
            ->leftJoin('a.fonction', 'f')
            ->leftJoin('f.roles', 'fr')
            ->where('u.nextcloudAccount = true')
            ->andWhere('u.isActive = true')
            ->getQuery()
            ->getResult();

        $users = [];
        foreach ($accounts as $account) {
            $membre = $account->getMembre();
            $uid = Subject::fromUsername($account->getUsername());
            $users[$uid] = new DirectoryUser(
                uid: $uid,
                // lldap refuses users without an email, and most netBS accounts
                // have none. Placeholder addresses use the reserved .invalid TLD
                // so nothing is ever delivered to them.
                email: BSUserIdentity::email($account) ?? sprintf('%s@no-email.invalid', $uid),
                displayName: BSUserIdentity::displayName($account),
                firstName: $membre ? (string) $membre->getPrenom() : '',
                lastName: $membre ? (string) $membre->_getNom() : '',
                membreId: $membre?->getId(),
                roles: array_values(array_intersect(
                    array_keys(DirectoryProjection::ROLE_GROUPS),
                    $account->getRolesAsString(),
                )),
            );
        }

        return $users;
    }
}
