<?php

namespace App\Tests\Support;

use App\Entity\BSUser;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\SecureBundle\Entity\Role;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Shared helper for the password-feature functional tests. The suite has no
 * fixtures, so each test provisions exactly the user it needs directly in
 * netbs5_test. Any same-username (or same-email) row is deleted first —
 * bypassing the soft-delete filter — so re-runs don't trip the UNIQUE indexes.
 */
trait UserFactoryTrait
{
    /** @param string[] $roles role names to attach, e.g. ['ROLE_USER', 'ROLE_ADMIN'] */
    protected function persistUser(
        KernelBrowser $client,
        string $username,
        ?string $email = null,
        ?string $plainPassword = null,
        array $roles = [],
        bool $active = true,
    ): BSUser {
        /** @var EntityManagerInterface $em */
        $em     = $client->getContainer()->get('doctrine.orm.entity_manager');
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $em->getFilters()->disable('softdeleteable');
        $em->createQuery('DELETE FROM App\\Entity\\BSUser u WHERE u.username = :u')
            ->setParameter('u', $username)->execute();
        if ($email !== null) {
            $em->createQuery('DELETE FROM App\\Entity\\BSUser u WHERE u.email = :e')
                ->setParameter('e', $email)->execute();
        }
        $em->getFilters()->enable('softdeleteable');

        $user = new BSUser();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setIsActive($active);
        $user->setPassword(
            $plainPassword !== null ? $hasher->hashPassword($user, $plainPassword) : 'placeholder-hash',
        );
        foreach ($roles as $roleName) {
            $user->addRole($em->getRepository(Role::class)->findOneBy(['role' => $roleName]));
        }
        $em->persist($user);
        $em->flush();

        return $user;
    }

    protected function reloadUser(KernelBrowser $client, string $username): ?BSUser
    {
        return $client->getContainer()->get('doctrine.orm.entity_manager')
            ->getRepository(BSUser::class)
            ->findOneBy(['username' => $username]);
    }
}
