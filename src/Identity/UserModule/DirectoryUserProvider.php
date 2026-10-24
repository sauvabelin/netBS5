<?php

declare(strict_types=1);

namespace App\Identity\UserModule;

use App\Entity\BSUser;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\AuthBundle\Contract\DirectoryUserProviderInterface;
use NetBS\AuthBundle\Contract\Subject;
use NetBS\AuthBundle\Directory\DirectoryUser;

/**
 * Directory users: active accounts with nextcloud_account = 1.
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
            ->select('u', 'm')
            ->from(BSUser::class, 'u')
            ->leftJoin('u.membre', 'm')
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
            );
        }

        return $users;
    }
}
