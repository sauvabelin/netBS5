<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\APMBSReservation;
use App\Service\APMBSClientLinks;
use App\Tests\Support\APMBSFixturesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class APMBSClientLinksTest extends TestCase
{
    use APMBSFixturesTrait;

    public function test_token_is_valid_for_the_same_reservation_and_action(): void
    {
        $links = $this->links();
        $reservation = $this->reservation(42);

        $this->assertTrue($links->isValid($reservation, 'accept', $links->token($reservation, 'accept')));
        $this->assertTrue($links->isValid($reservation, 'reject', $links->token($reservation, 'reject')));
    }

    public function test_token_does_not_carry_over_to_another_action_or_reservation(): void
    {
        $links = $this->links();
        $token = $links->token($this->reservation(42), 'accept');

        $this->assertFalse($links->isValid($this->reservation(42), 'reject', $token));
        $this->assertFalse($links->isValid($this->reservation(43), 'accept', $token));
    }

    public function test_token_is_void_once_the_proposed_dates_change(): void
    {
        $links = $this->links();
        $reservation = $this->reservation(42);
        $token = $links->token($reservation, 'accept');

        $reservation->setEnd(new \DateTime('2026-11-13 16:00'));

        $this->assertFalse($links->isValid($reservation, 'accept', $token));
    }

    public function test_rejects_missing_tampered_or_foreign_tokens(): void
    {
        $links = $this->links();
        $reservation = $this->reservation(42);
        $token = $links->token($reservation, 'accept');

        $this->assertFalse($links->isValid($reservation, 'accept', null));
        $this->assertFalse($links->isValid($reservation, 'accept', ''));
        $this->assertFalse($links->isValid($reservation, 'accept', substr($token, 0, -1) . 'x'));
        $this->assertFalse($this->links('other-secret')->isValid($reservation, 'accept', $token));
    }

    public function test_unknown_action_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->links()->token($this->reservation(42), 'delete');
    }

    public function test_url_carries_the_token_but_not_the_email(): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects($this->once())->method('generate')
            ->with('apmbs.api.client.answer', $this->callback(fn (array $p) => $p['id'] === 42 && $p['action'] === 'accept' && $p['token'] !== ''), UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://netbs.example/answer');

        $this->assertSame('https://netbs.example/answer', (new APMBSClientLinks('secret', $urls))->url($this->reservation(42), 'accept'));
    }

    private function links(string $secret = 'secret'): APMBSClientLinks
    {
        return new APMBSClientLinks($secret, $this->createMock(UrlGeneratorInterface::class));
    }

    private function reservation(int $id): APMBSReservation
    {
        return $this->makeReservation(APMBSReservation::MODIFICATION_PENDING, $id);
    }
}
