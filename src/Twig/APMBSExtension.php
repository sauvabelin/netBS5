<?php

namespace App\Twig;

use App\Service\APMBSClientLinks;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class APMBSExtension extends AbstractExtension
{
    public function __construct(private APMBSClientLinks $clientLinks)
    {
    }

    public function getFunctions(): array
    {
        return [
            // apmbs_client_url(reservation, 'accept'|'reject'): signed link for the modification email
            new TwigFunction('apmbs_client_url', [$this->clientLinks, 'url']),
        ];
    }
}
