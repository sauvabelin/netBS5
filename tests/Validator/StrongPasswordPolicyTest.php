<?php

namespace App\Tests\Validator;

use NetBS\SecureBundle\Validator\Constraints\StrongPassword;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Pins the shared password policy's two configured thresholds (min length 10,
 * PasswordStrength >= MEDIUM). That the policy is enforced at every entry point
 * is covered end-to-end by WeakPasswordRejectionTest.
 */
class StrongPasswordPolicyTest extends KernelTestCase
{
    private const STRONG    = 'C0rrect-Horse-Battery-Staple!';
    private const TOO_SHORT = 'Ab3$x';         // < 10 chars: trips the length floor
    private const LOW_SCORE = 'aaaaaaaaaaaa';   // 12 chars but below MEDIUM strength

    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get('validator');
    }

    public function test_policy_rejects_short_and_weak_but_accepts_strong(): void
    {
        $this->assertGreaterThan(0, $this->validator->validate(self::TOO_SHORT, new StrongPassword())->count(), 'under 10 chars must be rejected');
        $this->assertGreaterThan(0, $this->validator->validate(self::LOW_SCORE, new StrongPassword())->count(), 'below-MEDIUM strength must be rejected');
        $this->assertCount(0, $this->validator->validate(self::STRONG, new StrongPassword()), 'a strong password must pass');
    }
}
