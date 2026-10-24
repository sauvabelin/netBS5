<?php

declare(strict_types=1);

namespace App\Tests\Identity\UserModule;

use App\Entity\BSUser;
use App\Identity\UserModule\IdentityUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

/**
 * The resolver must fetch BSUser only once per resolveBySub(), and map it with
 * the same rules as the LDAP directory (see BSUserIdentity).
 */
final class IdentityUserResolverTest extends TestCase
{
    public function testResolveBySubIssuesExactlyOneDoctrineFetch(): void
    {
        $user = $this->user(email: 'alice@example.com');

        $resolver = new IdentityUserResolver($this->emReturning($user, expectOnce: true));
        $dto = $resolver->resolveBySub('alice');

        $this->assertNotNull($dto);
        $this->assertSame('alice', $dto->sub);
        $this->assertSame('alice@example.com', $dto->email);
        $this->assertSame('alice', $dto->displayName);
        $this->assertFalse($dto->isDisabled);
    }

    public function testSubjectIsLowercased(): void
    {
        $dto = (new IdentityUserResolver($this->emReturning($this->user(username: 'Equipe'))))->resolveBySub('equipe');

        $this->assertSame('equipe', $dto->sub);
        $this->assertSame('equipe', $dto->preferredUsername);
    }

    public function testEmptyPersonalEmailFallsBackToBsEmail(): void
    {
        $user = $this->user(email: '', emailBS: 'alice@sauvabelin.ch');

        $dto = (new IdentityUserResolver($this->emReturning($user)))->resolveBySub('alice');

        $this->assertSame('alice@sauvabelin.ch', $dto->email);
    }

    private function user(?string $email = null, ?string $emailBS = null, string $username = 'alice'): BSUser
    {
        $user = $this->createMock(BSUser::class);
        $user->method('getUsername')->willReturn($username);
        $user->method('getMembre')->willReturn(null);
        $user->method('getEmail')->willReturn($email);
        $user->method('getEmailBS')->willReturn($emailBS);
        $user->method('getIsActive')->willReturn(true);
        return $user;
    }

    private function emReturning(?BSUser $user, bool $expectOnce = false): EntityManagerInterface
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($expectOnce ? $this->once() : $this->any())
            ->method('findOneBy')
            ->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(BSUser::class)->willReturn($repo);
        return $em;
    }
}
