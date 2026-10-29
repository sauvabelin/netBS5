<?php

namespace App\Service;

use App\Entity\APMBSReservation;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Links in the modification email that let the client accept or reject the
 * proposed dates without an account. The token is an HMAC over the
 * reservation, the action and the proposed dates: it can't be guessed, works
 * for one action only, and stops working once the intendant proposes other
 * dates. It doesn't depend on the URL, so the reverse proxy's view of
 * scheme/host doesn't matter. No expiry: the reservation status already
 * limits the link to one answer.
 */
class APMBSClientLinks {

    public const ACTIONS = ['accept', 'reject'];

    public function __construct(
        #[\SensitiveParameter] private readonly string $secret,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function url(APMBSReservation $reservation, string $action): string {
        return $this->urls->generate('apmbs.api.client.answer', [
            'id' => $reservation->getId(),
            'action' => $action,
            'token' => $this->token($reservation, $action),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function token(APMBSReservation $reservation, string $action): string {
        if (!in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException("Unknown client action \"$action\".");
        }

        $payload = implode('|', [
            'apmbs-client-answer',
            $reservation->getId(),
            $action,
            $reservation->getStart()->format('U'),
            $reservation->getEnd()->format('U'),
        ]);

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $this->secret, true)), '+/', '-_'), '=');
    }

    public function isValid(APMBSReservation $reservation, string $action, ?string $token): bool {
        return $token !== null && $token !== '' && hash_equals($this->token($reservation, $action), $token);
    }
}
