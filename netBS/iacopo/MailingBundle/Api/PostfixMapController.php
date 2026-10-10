<?php

declare(strict_types=1);

namespace Iacopo\MailingBundle\Api;

use Iacopo\MailingBundle\Service\PostfixVirtualMapBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the Postfix virtual alias map to the mail node, which pulls it on a
 * cron and installs it with `postmap lmdb:` (see sync-netbs-map.sh in the
 * sauvabelin/mailserver repo).
 *
 * Lives outside every firewall (not under /netBS or /api/v1/netBS): the only
 * client is a machine holding the shared token, checked below.
 */
final class PostfixMapController
{
    public function __construct(
        private readonly PostfixVirtualMapBuilder $builder,
        private readonly LoggerInterface $logger,
        private readonly string $expectedToken,
    ) {
    }

    #[Route('/mailing-export/postfix-map', name: 'iacopo.mailing.postfix_map', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        // Refuse to serve the member address list if the operator forgot to
        // configure the token, rather than exposing it to anyone.
        if ($this->expectedToken === '') {
            $this->logger->error('mailing.postfix_map: rejected - POSTFIX_MAP_TOKEN not configured');
            return $this->text('server misconfigured', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $presented = (string) preg_replace('/^Bearer\s+/i', '', (string) $request->headers->get('Authorization', ''));
        if ($presented === '' || !hash_equals($this->expectedToken, $presented)) {
            $this->logger->warning('mailing.postfix_map: rejected - bad or missing token', [
                'remote_addr' => $request->getClientIp(),
            ]);
            return $this->text('unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $map = $this->builder->build();
        foreach ($map->warnings as $warning) {
            $this->logger->warning('mailing.postfix_map: ' . $warning);
        }

        // An empty map means something upstream is broken; a 5xx makes the
        // mail node keep its last good map instead of installing it.
        if ($map->entries === []) {
            $this->logger->error('mailing.postfix_map: map is empty, refusing to serve it');
            return $this->text('map is empty', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $response = $this->text($map->render(), Response::HTTP_OK);
        $response->headers->set('X-Map-Entries', (string) count($map->entries));

        return $response;
    }

    private function text(string $body, int $status): Response
    {
        return new Response($body, $status, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
