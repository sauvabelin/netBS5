<?php

namespace NetBS\CoreBundle\Twig\Extension;

use NetBS\CoreBundle\Twig\Runtime\MailerRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MailerExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('email_stylesheet', [MailerRuntime::class, 'getEmailStylesheet']),
        ];
    }
}
