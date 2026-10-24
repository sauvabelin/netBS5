<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Twig;

use NetBS\AuthBundle\Service\OidcEndpoints;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class OidcEndpointsExtension extends AbstractExtension
{
    public function __construct(private readonly OidcEndpoints $endpoints)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('oidc_endpoints', fn (): array => [
                'issuer'        => $this->endpoints->issuer(),
                'discovery'     => $this->endpoints->discovery(),
                'authorization' => $this->endpoints->authorization(),
                'token'         => $this->endpoints->token(),
                'userinfo'      => $this->endpoints->userinfo(),
                'jwks'          => $this->endpoints->jwks(),
                'logout'        => $this->endpoints->logout(),
            ]),
        ];
    }
}
