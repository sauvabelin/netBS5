<?php

namespace App\Tests\Validator;

use App\Model\AdminChangePassword;
use NetBS\SecureBundle\Model\ChangePassword;
use NetBS\SecureBundle\Validator\Constraints\StrongPassword;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Verifies the shared password policy is enforced and wired to every model-backed
 * entry point. ChangePassword backs the reset flow and the my-account self-change;
 * AdminChangePassword backs the admin change; UserType applies the same
 * `new StrongPassword()` object on its form field (covered here by validating the
 * constraint directly, since the form field isn't a class property).
 */
class StrongPasswordPolicyTest extends KernelTestCase
{
    private const STRONG = 'C0rrect-Horse-Battery-Staple!';
    private const WEAK_LOW_ENTROPY = 'aaaaaaaaaaaa'; // 12 chars: passes length, fails strength
    private const TOO_SHORT = 'Ab3$x';               // strong-ish but under 10 chars

    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get('validator');
    }

    /** @dataProvider rejectedPasswords */
    public function test_change_password_newPassword_is_rejected(string $password): void
    {
        $violations = $this->validator->validatePropertyValue(ChangePassword::class, 'newPassword', $password);
        $this->assertGreaterThan(0, $violations->count(), "Expected '$password' to be rejected on ChangePassword::newPassword.");
    }

    /** @dataProvider rejectedPasswords */
    public function test_admin_change_password_is_rejected(string $password): void
    {
        $violations = $this->validator->validatePropertyValue(AdminChangePassword::class, 'password', $password);
        $this->assertGreaterThan(0, $violations->count(), "Expected '$password' to be rejected on AdminChangePassword::password.");
    }

    /** @dataProvider rejectedPasswords */
    public function test_constraint_object_rejects(string $password): void
    {
        // The exact object UserType attaches to its password field.
        $violations = $this->validator->validate($password, new StrongPassword());
        $this->assertGreaterThan(0, $violations->count(), "Expected '$password' to be rejected by StrongPassword.");
    }

    public function test_strong_password_is_accepted_everywhere(): void
    {
        $this->assertCount(0, $this->validator->validatePropertyValue(ChangePassword::class, 'newPassword', self::STRONG));
        $this->assertCount(0, $this->validator->validatePropertyValue(AdminChangePassword::class, 'password', self::STRONG));
        $this->assertCount(0, $this->validator->validate(self::STRONG, new StrongPassword()));
    }

    public static function rejectedPasswords(): array
    {
        return [
            'empty'        => [''],
            'too short'    => [self::TOO_SHORT],
            'low entropy'  => [self::WEAK_LOW_ENTROPY],
            'common word'  => ['password123'],
        ];
    }
}
