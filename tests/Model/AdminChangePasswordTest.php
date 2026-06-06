<?php

namespace App\Tests\Model;

use App\Model\AdminChangePassword;
use PHPUnit\Framework\TestCase;

class AdminChangePasswordTest extends TestCase
{
    public function test_force_change_defaults_to_false(): void
    {
        // A freshly-built model (e.g. before the form binds the unchecked
        // switch) must report a concrete boolean, never an uninitialised null —
        // the controller reads isForceChange() unconditionally.
        $this->assertFalse((new AdminChangePassword())->isForceChange());
    }
}
