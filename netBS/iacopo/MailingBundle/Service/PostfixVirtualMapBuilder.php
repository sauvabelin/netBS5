<?php

declare(strict_types=1);

namespace Iacopo\MailingBundle\Service;

use Iacopo\MailingBundle\Repository\MailingListRepository;

/**
 * Builds a fully-expanded Postfix virtual alias map from the active mailing
 * lists. Every base address and alias maps directly to its final recipients,
 * so Postfix never has to recurse through list-to-list hops (nesting and
 * cycle handling are done once, by MailingTargetResolver).
 *
 * The rendered output is meant to be fed to `postmap lmdb:<file>` on the
 * mail host and referenced from `virtual_alias_maps`.
 */
class PostfixVirtualMapBuilder
{
    // Anything Postfix's table parser could misread (whitespace splits key from
    // value, commas split recipients) or that isn't a plain local@domain.
    private const SAFE_ADDRESS = '/^[^\s,"<>@]+@[^\s,"<>@]+$/';

    public function __construct(
        private readonly MailingListRepository $mailingLists,
        private readonly MailingTargetResolver $resolver,
    ) {
    }

    public function build(): PostfixVirtualMap
    {
        $entries = [];
        $warnings = [];

        foreach ($this->mailingLists->findBy(['active' => true]) as $list) {
            $listLabel = sprintf('"%s" (#%d)', $list->getName(), $list->getId());

            $recipients = [];
            foreach ($this->resolver->resolveMailingList($list) as $email) {
                $email = strtolower(trim($email));
                if (!preg_match(self::SAFE_ADDRESS, $email)) {
                    $warnings[] = sprintf('%s: skipped malformed recipient "%s"', $listLabel, $email);
                    continue;
                }
                $recipients[$email] = true;
            }

            $sources = [$list->getBaseAddress()];
            foreach ($list->getAliases() as $alias) {
                $sources[] = $alias->getAddress();
            }

            foreach ($sources as $source) {
                $source = strtolower(trim((string) $source));
                if (!preg_match(self::SAFE_ADDRESS, $source)) {
                    $warnings[] = sprintf('%s: skipped malformed address "%s"', $listLabel, $source);
                    continue;
                }

                // An address that expands to itself would be delivered locally
                // instead of being forwarded — drop it rather than bounce.
                $targets = $recipients;
                if (isset($targets[$source])) {
                    unset($targets[$source]);
                    $warnings[] = sprintf('%s: dropped self-reference to %s', $listLabel, $source);
                }

                // postmap rejects keys without a value; leaving the address out
                // makes Postfix reject mail to it instead.
                if ($targets === []) {
                    $warnings[] = sprintf('%s: %s has no recipients, left out of the map', $listLabel, $source);
                    continue;
                }

                if (isset($entries[$source])) {
                    $warnings[] = sprintf('%s: %s is also used by another list, recipients merged', $listLabel, $source);
                    $targets += $entries[$source];
                }

                ksort($targets);
                $entries[$source] = $targets;
            }
        }

        ksort($entries);

        return new PostfixVirtualMap(
            array_map('array_keys', $entries),
            $warnings,
        );
    }
}
