<?php

namespace App\Tests\Support;

use App\Entity\BSMembre;
use App\Entity\BSUser;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Entity\Role;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * In-memory users and roles for voter unit tests. The role tree mirrors the
 * production one (ROLE_SG → ROLE_*_EVERYWHERE → ROLE_*, ROLE_ADMIN → ROLE_USER).
 * Needs a booted kernel only for the bundle configs.
 */
trait VoterTestTrait
{
    /** @var array<string, Role> */
    private array $roleTree = [];

    private int $nextUserId = 1000;

    protected function role(string $name): Role
    {
        if (!$this->roleTree) {
            $this->buildRoleTree();
        }

        return $this->roleTree[$name] ??= new Role($name);
    }

    private function buildRoleTree(): void
    {
        $link = function (string $parent, string ...$children): void {
            foreach ($children as $child) {
                $this->roleTree[$parent] ??= new Role($parent);
                $this->roleTree[$child] ??= new Role($child);
                $this->roleTree[$parent]->addChild($this->roleTree[$child]);
            }
        };

        $link('ROLE_ADMIN', 'ROLE_USER', 'ROLE_COMMANDANT');
        $link('ROLE_COMMANDANT', 'ROLE_QM');
        $link('ROLE_QM', 'ROLE_SG', 'ROLE_TRESORIER', 'ROLE_MAILING');
        $link('ROLE_SG', 'ROLE_CREATE_EVERYWHERE', 'ROLE_READ_EVERYWHERE', 'ROLE_UPDATE_EVERYWHERE', 'ROLE_DELETE_EVERYWHERE');
        $link('ROLE_CREATE_EVERYWHERE', 'ROLE_CREATE', 'ROLE_CREATE_ATTRIBUTION');
        $link('ROLE_READ_EVERYWHERE', 'ROLE_READ');
        $link('ROLE_UPDATE_EVERYWHERE', 'ROLE_UPDATE');
        $link('ROLE_DELETE_EVERYWHERE', 'ROLE_DELETE');
    }

    protected function user(array $roles = [], ?BSMembre $membre = null): BSUser
    {
        $user = new BSUser();
        $id = new \ReflectionProperty($user, 'id');
        $id->setAccessible(true);
        $id->setValue($user, $this->nextUserId++);
        $user->setUsername('voter-user-' . $user->getId());
        foreach ($roles as $role) {
            $user->addRole($this->role($role));
        }
        if ($membre) {
            $user->setMembre($membre);
        }

        return $user;
    }

    protected function vote(VoterInterface $voter, BSUser $user, object $subject, string $attribute): int
    {
        return $voter->vote(new UsernamePasswordToken($user, 'netbs', []), $subject, [$attribute]);
    }

    protected function fichierConfig(): FichierConfig
    {
        return new FichierConfig(static::getContainer()->getParameter('netbs.fichier.config'));
    }

    protected function secureConfig(): SecureConfig
    {
        return new SecureConfig(static::getContainer()->getParameter('netbs.secure.config'));
    }

    protected function assertGranted(int $vote): void
    {
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $vote);
    }

    protected function assertDenied(int $vote): void
    {
        $this->assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }
}
