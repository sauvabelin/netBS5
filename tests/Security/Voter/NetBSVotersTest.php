<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\BSUser;
use App\Tests\Support\VoterTestTrait;
use Iacopo\MailingBundle\Entity\MailingList;
use Iacopo\MailingBundle\Voter\MailingListVoter;
use NetBS\CoreBundle\Entity\DynamicList;
use NetBS\CoreBundle\Entity\ExportConfiguration;
use NetBS\CoreBundle\Entity\Parameter;
use NetBS\CoreBundle\Voter\DynamicListVoter;
use NetBS\CoreBundle\Voter\ExportConfigurationVoter;
use NetBS\CoreBundle\Voter\ParameterVoter;
use NetBS\SecureBundle\Entity\Autorisation;
use NetBS\SecureBundle\Entity\Role;
use NetBS\SecureBundle\Voter\AutorisationVoter;
use NetBS\SecureBundle\Voter\BSRoleVoter;
use NetBS\SecureBundle\Voter\CRUD;
use NetBS\SecureBundle\Voter\UserVoter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class NetBSVotersTest extends KernelTestCase
{
    use VoterTestTrait;

    protected function setUp(): void
    {
        self::bootKernel();
    }

    // --- NetBSVoter base rules (exercised through MailingListVoter, which grants these roles nothing itself)

    /** @dataProvider everywhereRoles */
    public function test_everywhere_role_grants_only_its_operation(string $role, string $granted): void
    {
        $voter = new MailingListVoter();
        $user  = $this->user([$role]);

        foreach (CRUD::toArray() as $operation) {
            $this->assertSame(
                $operation === $granted ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED,
                $this->vote($voter, $user, new MailingList(), $operation),
                $operation,
            );
        }
    }

    public static function everywhereRoles(): iterable
    {
        yield [  'ROLE_READ_EVERYWHERE', CRUD::READ];
        yield ['ROLE_UPDATE_EVERYWHERE', CRUD::UPDATE];
        yield ['ROLE_CREATE_EVERYWHERE', CRUD::CREATE];
        yield ['ROLE_DELETE_EVERYWHERE', CRUD::DELETE];
    }

    public function test_admin_overrides_everything(): void
    {
        $admin = $this->user(['ROLE_ADMIN']);

        $this->assertGranted($this->vote(new ParameterVoter(), $admin, new Parameter('ns', 'key', 'v'), CRUD::DELETE));
        $this->assertGranted($this->vote(new BSRoleVoter($this->secureConfig()), $admin, new Role('ROLE_X'), CRUD::UPDATE));
    }

    // --- Temporary password -----------------------------------------------------

    public function test_a_temporary_password_denies_everything(): void
    {
        $admin = $this->user(['ROLE_ADMIN']);
        $admin->setNewPasswordRequired(true);

        $this->assertSame([], $admin->getAllRoles());
        $this->assertFalse($admin->hasRole('ROLE_USER'));
        $this->assertDenied($this->vote(new ParameterVoter(), $admin, new Parameter('ns', 'key', 'v'), CRUD::READ));
        $this->assertDenied($this->vote(new UserVoter($this->secureConfig()), $admin, $admin, CRUD::READ), 'not even their own account');
        $this->assertSame(VoterInterface::ACCESS_DENIED, (new \NetBS\SecureBundle\Voter\RoleVoter())->vote(
            new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($admin, 'netbs', []), null, ['ROLE_USER'],
        ));

        $admin->setNewPasswordRequired(false);
        $this->assertTrue($admin->hasRole('ROLE_ADMIN'), 'the roles come back with the new password');
    }

    public function test_non_crud_attributes_and_foreign_subjects_are_abstained(): void
    {
        $voter = new ParameterVoter();
        $admin = $this->user(['ROLE_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($voter, $admin, new Parameter('ns', 'k', 'v'), 'ROLE_ADMIN'));
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($voter, $admin, new \stdClass(), CRUD::READ));
    }

    // --- Admin-only voters ----------------------------------------------------

    /** @dataProvider adminOnlySubjects */
    public function test_admin_only_subjects_refuse_sg(\Closure $voter, \Closure $subject): void
    {
        $voter   = $voter($this);
        $subject = $subject();

        $this->assertDenied($this->vote($voter, $this->user(['ROLE_SG']), $subject, CRUD::UPDATE));
        $this->assertGranted($this->vote($voter, $this->user(['ROLE_ADMIN']), $subject, CRUD::UPDATE));
    }

    public static function adminOnlySubjects(): iterable
    {
        yield 'autorisation' => [fn (self $t) => new AutorisationVoter($t->secureConfig()), fn () => new Autorisation()];
        yield 'role' => [fn (self $t) => new BSRoleVoter($t->secureConfig()), fn () => new Role('ROLE_X')];
        yield 'parameter' => [fn () => new ParameterVoter(), fn () => new Parameter('ns', 'key', 'v')];
    }

    // --- Owner-only voters ------------------------------------------------------

    public function test_user_manages_only_their_own_account(): void
    {
        $voter = new UserVoter($this->secureConfig());
        $user  = $this->user(['ROLE_USER']);

        $this->assertGranted($this->vote($voter, $user, $user, CRUD::UPDATE));
        $this->assertDenied($this->vote($voter, $user, $this->user(['ROLE_USER']), CRUD::READ));
        $this->assertDenied($this->vote($voter, $this->user(['ROLE_SG']), $user, CRUD::UPDATE));
    }

    /** @dataProvider ownedSubjects */
    public function test_owned_subject_is_only_for_its_owner(\Closure $voter, \Closure $subject): void
    {
        $owner   = $this->user(['ROLE_USER']);
        $subject = $subject($owner);
        $voter   = $voter();

        $this->assertGranted($this->vote($voter, $owner, $subject, CRUD::UPDATE));
        $this->assertDenied($this->vote($voter, $this->user(['ROLE_USER']), $subject, CRUD::READ));
        $this->assertDenied($this->vote($voter, $this->user(['ROLE_SG']), $subject, CRUD::READ));
    }

    public static function ownedSubjects(): iterable
    {
        yield 'dynamic list' => [fn () => new DynamicListVoter(), function (BSUser $owner) {
            $list = new DynamicList();
            $list->setOwner($owner);

            return $list;
        }];
        yield 'export configuration' => [fn () => new ExportConfigurationVoter(), function (BSUser $owner) {
            $config = new ExportConfiguration();
            $config->setUser($owner);

            return $config;
        }];
    }

    // --- Role-gated voters ------------------------------------------------------

    public function test_mailing_lists_need_role_mailing(): void
    {
        $voter = new MailingListVoter();

        $this->assertDenied($this->vote($voter, $this->user(['ROLE_TRESORIER']), new MailingList(), CRUD::READ));
        $this->assertGranted($this->vote($voter, $this->user(['ROLE_MAILING']), new MailingList(), CRUD::UPDATE));
        $this->assertGranted($this->vote($voter, $this->user(['ROLE_QM']), new MailingList(), CRUD::DELETE));
    }
}
