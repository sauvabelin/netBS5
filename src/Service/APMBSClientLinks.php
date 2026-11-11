<?php

namespace App\Service;

use App\Entity\APMBSReservation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Signed links sent to clients so they can accept or refuse a modification proposed by an intendant.
 *
 * The token is an HMAC of the reservation and its proposed dates, so it can't be guessed from the
 * reservation id and email, and links from an older proposal stop working once the dates change again.
 * Rotating APP_SECRET invalidates all links already sent.
 */
class APMBSClientLinks
{
    private const ROUTES = [
        'accept' => 'apmbs.api.client.accept',
        'reject' => 'apmbs.api.client.reject',
    ];

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%kernel.secret%')] private string $secret,
    ) {
    }

    public function url(APMBSReservation $reservation, string $action): string
    {
        if (!isset(self::ROUTES[$action])) {
            throw new \InvalidArgumentException("Unknown client action '$action'");
        }

        return $this->urlGenerator->generate(self::ROUTES[$action], [
            'id'    => $reservation->getId(),
            'token' => $this->token($reservation),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function isValid(APMBSReservation $reservation, string $token): bool
    {
        return hash_equals($this->token($reservation), $token);
    }

    private function token(APMBSReservation $reservation): string
    {
        // Wall-clock format, exactly what the datetime columns store, so the token is the same
        // whether the dates come from the form that set them or are reloaded from the database
        $payload = implode('|', [
            'apmbs-client-response',
            $reservation->getId(),
            $reservation->getEmail(),
            $reservation->getStart()->format('Y-m-d H:i:s'),
            $reservation->getEnd()->format('Y-m-d H:i:s'),
        ]);

        $hmac = hash_hmac('sha256', $payload, $this->secret, true);
        return rtrim(strtr(base64_encode($hmac), '+/', '-_'), '=');
    }
}
