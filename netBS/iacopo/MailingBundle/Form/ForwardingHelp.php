<?php

declare(strict_types=1);

namespace Iacopo\MailingBundle\Form;

use Iacopo\MailingBundle\Service\MailingDomains;

/**
 * Help text for list address fields: addresses on a forwarded domain only
 * receive mail once the domain's host forwards them to the mail node.
 */
final class ForwardingHelp
{
    public static function for(MailingDomains $domains): ?string
    {
        $sentences = [];
        foreach ($domains->forwardedDomains() as $domain => $target) {
            $sentences[] = sprintf(
                'Une adresse en @%s ne reçoit rien tant que l\'hébergeur de %s ne la renvoie pas vers la même adresse en @%s.',
                $domain,
                $domain,
                $target,
            );
        }

        return $sentences === [] ? null : implode(' ', $sentences);
    }
}
