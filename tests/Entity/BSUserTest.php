<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\BSUser;
use PHPUnit\Framework\TestCase;

/**
 * Covers the username / loginUsername invariants on BSUser.
 *
 * Rationale (see BSUser::setUsername docblock):
 *   - `username` is the permanent OIDC subject. It is set exactly once and
 *     attempts to reassign it must throw rather than silently no-op.
 *   - `loginUsername` is the editable login handle. Empty / null collapses
 *     to whatever the permanent `username` is, so a freshly constructed
 *     entity always presents a meaningful login handle.
 *   - Bootstrapping must work in either order: setting the username first
 *     should populate the login handle (the historical happy path for
 *     fixture loaders), and setting the login handle first on a blank
 *     entity must also bootstrap the permanent username so the row is
 *     valid against the NOT NULL `username` column.
 */
final class BSUserTest extends TestCase
{
    public function testSetUsernameOnNewEntitySetsBothUsernameAndLoginUsername(): void
    {
        $user = new BSUser();
        $user->setUsername('alice');

        $this->assertSame('alice', $user->getUsername());
        $this->assertSame('alice', $user->getLoginUsername(),
            'setUsername on a blank entity must bootstrap the editable login handle');
    }

    public function testSetUsernameOnAlreadySetEntityWithSameValueIsIdempotent(): void
    {
        $user = new BSUser();
        $user->setUsername('alice');

        // Re-assigning the same value is allowed: fixture loaders and
        // hydration paths can replay setUsername without surprises.
        $user->setUsername('alice');

        $this->assertSame('alice', $user->getUsername());
    }

    public function testSetUsernameOnAlreadySetEntityWithDifferentValueThrowsLogicException(): void
    {
        $user = new BSUser();
        $user->setUsername('alice');

        $this->expectException(\LogicException::class);
        // The exception message includes both the current and the attempted
        // value to help debug data-importer mistakes.
        $this->expectExceptionMessageMatches('/immutable once set/');

        $user->setUsername('bob');
    }

    public function testSetLoginUsernameEmptyStringFallsBackToUsername(): void
    {
        $user = new BSUser();
        $user->setUsername('alice');

        $user->setLoginUsername('');

        $this->assertSame('alice', $user->getLoginUsername(),
            'empty string must collapse to the permanent username');
    }

    public function testSetLoginUsernameNullFallsBackToUsername(): void
    {
        $user = new BSUser();
        $user->setUsername('alice');

        $user->setLoginUsername(null);

        $this->assertSame('alice', $user->getLoginUsername(),
            'null must collapse to the permanent username');
    }

    public function testSetLoginUsernameWithExplicitValueOverridesFallback(): void
    {
        $user = new BSUser();
        $user->setUsername('alice');

        $user->setLoginUsername('alice.new');

        $this->assertSame('alice.new', $user->getLoginUsername());
        // And the permanent username is still pinned to its original value.
        $this->assertSame('alice', $user->getUsername());
    }

    public function testSetLoginUsernameFirstBootstrapsPermanentUsername(): void
    {
        // Documents the other entry point: setting the login handle on a
        // blank entity must also seed `username` so the row is valid against
        // the NOT NULL constraint on the permanent identifier.
        $user = new BSUser();
        $user->setLoginUsername('alice');

        $this->assertSame('alice', $user->getUsername());
        $this->assertSame('alice', $user->getLoginUsername());
    }
}
