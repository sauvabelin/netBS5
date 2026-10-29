<?php

declare(strict_types=1);

namespace Iacopo\MailingBundle\Service;

use Iacopo\MailingBundle\Repository\MailingListRepository;

/**
 * Builds a fully-expanded Postfix virtual alias map from the active mailing
 * lists. Every base address and alias maps directly to its final recipients:
 * nested lists are expanded by MailingTargetResolver, and a recipient that is
 * itself a list address (typed in as a plain email) is replaced here by that
 * list's recipients, cycles cut. Postfix therefore never chains list to list;
 * the one key it still finds among the recipients is a list address that
 * lists itself, where Postfix stops and relays to the real mailbox.
 *
 * Lists on a forwarded domain (see MailingDomains) are keyed by the address
 * the forward delivers to, and never list themselves: that copy would go back
 * to the forwarding host, which forwards it to the node again.
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
        private readonly MailingDomains $domains,
    ) {
    }

    public function build(): PostfixVirtualMap
    {
        /** @var array<string, array<string, true>> $direct source => recipients, before flattening */
        $direct = [];
        $warnings = [];

        foreach ($this->mailingLists->findBy(['active' => true]) as $list) {
            $listLabel = sprintf('"%s" (#%d)', $list->getName(), $list->getId());

            // The resolver returns addresses already lowercased and trimmed.
            $recipients = [];
            foreach ($this->resolver->resolveMailingList($list) as $email) {
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
                if (!$this->domains->allows($source)) {
                    $warnings[] = sprintf('%s: skipped %s, its domain is not relayed by the mail node', $listLabel, $source);
                    continue;
                }

                if (isset($direct[$source])) {
                    $warnings[] = sprintf('%s: %s is also used by another list, recipients merged', $listLabel, $source);
                }
                $direct[$source] = ($direct[$source] ?? []) + $recipients;
            }
        }

        $entries = [];
        $cutLoops = [];
        foreach (array_keys($direct) as $source) {
            $targets = $this->flatten($source, $direct, [$source], $cutLoops);
            if ($this->domains->isForwarded($source)) {
                unset($targets[$source]);
            }

            // postmap rejects keys without a value; leaving the address out
            // makes Postfix reject mail to it instead.
            if ($targets === []) {
                $warnings[] = sprintf('%s has no recipients, left out of the map', $source);
                continue;
            }

            ksort($targets);
            $entries[$this->domains->nodeAddress($source)] = array_keys($targets);
        }

        foreach (array_keys($cutLoops) as $pair) {
            $warnings[] = sprintf('%s include each other, loop cut', str_replace('|', ' and ', $pair));
        }

        ksort($entries);

        return new PostfixVirtualMap($entries, $warnings);
    }

    /**
     * Recipients of $source with every other list address replaced by that
     * list's own recipients.
     *
     * @param array<string, array<string, true>> $direct
     * @param list<string>                       $path     list addresses being expanded, to cut cycles
     * @param array<string, true>                $cutLoops "a|b" pairs found including each other
     * @return array<string, true>
     */
    private function flatten(string $source, array $direct, array $path, array &$cutLoops): array
    {
        $out = [];
        foreach (array_keys($direct[$source]) as $recipient) {
            // A list that lists itself: Postfix stops expanding there and relays
            // the copy to the real mailbox (e.g. info@ also read at Hostpoint).
            if ($recipient === $source || !isset($direct[$recipient])) {
                $out[$recipient] = true;
                continue;
            }

            if (in_array($recipient, $path, true)) {
                $cutLoops[min($source, $recipient) . '|' . max($source, $recipient)] = true;
                continue;
            }

            $out += $this->flatten($recipient, $direct, [...$path, $recipient], $cutLoops);
        }

        return $out;
    }
}
