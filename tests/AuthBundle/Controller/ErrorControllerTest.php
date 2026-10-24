<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Controller;

use NetBS\AuthBundle\Controller\Identity\ErrorController;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the OIDC error controller does not reflect attacker-supplied
 * `error_description` / `error_hint` / `error_debug` query parameters
 * (phishing-vector regression test for I19).
 */
final class ErrorControllerTest extends TestCase
{
    public function testNormaliseCodeStripsHostileCharacters(): void
    {
        $ref = new \ReflectionMethod(ErrorController::class, 'normaliseCode');
        $controller = (new \ReflectionClass(ErrorController::class))->newInstanceWithoutConstructor();

        // Casing is normalised.
        self::assertSame('access_denied', $ref->invoke($controller, 'Access_Denied'));
        // Non [a-z0-9_] chars (spaces, tags, punctuation) are stripped. Junk
        // appended to a valid code therefore produces a value that no longer
        // matches the whitelist — i.e. it cannot smuggle a curated message.
        $crafted = $ref->invoke($controller, 'access_denied<script>alert(1)</script>');
        $messages = (new \ReflectionClassConstant(ErrorController::class, 'ERROR_MESSAGES'))->getValue();
        self::assertArrayNotHasKey($crafted, $messages);
        self::assertStringNotContainsString('<', $crafted);
        self::assertStringNotContainsString('>', $crafted);
        // Spaces gone, lowercased.
        self::assertSame('accessdenied', $ref->invoke($controller, 'access denied'));
    }

    public function testKnownErrorCodesAreAllMapped(): void
    {
        $ref = new \ReflectionClassConstant(ErrorController::class, 'ERROR_MESSAGES');
        /** @var array<string,string> $messages */
        $messages = $ref->getValue();

        $expected = [
            'invalid_request', 'invalid_client', 'invalid_grant', 'unauthorized_client',
            'unsupported_response_type', 'unsupported_grant_type', 'invalid_scope',
            'access_denied', 'server_error', 'temporarily_unavailable',
            'login_required', 'consent_required', 'interaction_required',
            'account_selection_required',
        ];

        foreach ($expected as $code) {
            self::assertArrayHasKey($code, $messages, "Missing curated message for $code");
            self::assertNotSame('', $messages[$code]);
        }
    }

    public function testNoMessageContainsHttpEscapeChars(): void
    {
        // Sanity: curated messages are plain text — no HTML, no URL fragments,
        // so nothing surprising even if a future template change drops auto-escape.
        $messages = (new \ReflectionClassConstant(ErrorController::class, 'ERROR_MESSAGES'))->getValue();
        foreach ($messages as $code => $msg) {
            self::assertDoesNotMatchRegularExpression('/[<>]|https?:/i', $msg, "Suspect content in $code");
        }
    }
}
