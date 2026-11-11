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

    /**
     * Logs in through the real login form, so the session records its login time like a
     * real password login does (loginUser() skips that).
     */
    protected function passwordLogin(KernelBrowser $client, string $username, string $password): void
    {
        $crawler = $client->request('GET', '/netBS/secure/login');
        $form = $crawler->filter('form')
            ->reduce(fn ($f) => $f->filter('input[name="_username"]')->count() > 0)
            ->form();
        $form['_username'] = $username;
        $form['_password'] = $password;
        $client->submit($form);
    }

    /**
     * Submits the forgot-password form for $username and returns the token from
     * the reset link in the single email sent. Call after enableProfiler() so the
     * mailer assertions can read the POST response's profile.
     */
    protected function requestResetAndExtractToken(KernelBrowser $client, string $username): string
    {
        $crawler = $client->request('GET', '/netBS/secure/forgot-password');
        $form = $crawler->selectButton('Envoyer le lien')->form();
        $form['reset_password_request_form[username]'] = $username;
        $client->submit($form);

        $this->assertEmailCount(1);
        $body = $this->getMailerMessage(0)->getHtmlBody();
        $this->assertSame(1, preg_match('#/reset-password/([A-Za-z0-9_\-]+)#', $body, $m), 'email must contain a reset link');

        return $m[1];
    }
}
