<?php

declare(strict_types=1);

namespace Iacopo\MailingBundle\Service;

/**
 * The domains list addresses may use (`relay_domains` in the mailserver repo's
 * main.cf, minus the SRS one, plus the forwarded domains below). A list address
 * on any other domain would be exported but never receive mail, so the UI
 * refuses it and the export skips it.
 *
 * A forwarded domain keeps its MX elsewhere (sauvabelin.ch stays at Hostpoint),
 * which forwards each list address once to the same local part on a domain the
 * mail node handles (`role@sauvabelin.ch` => `role@alias.sauvabelin.ch`). The
 * export writes those lists under the forwarding target.
 */
class MailingDomains
{
    /** @var list<string> */
    private readonly array $domains;

    /** @var array<string, string> domain => domain its list mail is forwarded to */
    private readonly array $forwarded;

    /**
     * @param list<string> $domains   from MAILING_DOMAINS, comma-separated
     * @param list<string> $forwarded from MAILING_FORWARDED_DOMAINS, comma-separated `domain=target` pairs
     */
    public function __construct(array $domains, array $forwarded = [])
    {
        $this->domains = array_values(array_filter(array_map(
            static fn ($domain): string => strtolower(trim((string) $domain)),
            $domains,
        )));

        $pairs = [];
        foreach ($forwarded as $pair) {
            $parts = array_map('trim', explode('=', strtolower((string) $pair), 2));
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $pairs[$parts[0]] = $parts[1];
            }
        }
        $this->forwarded = $pairs;
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->domains;
    }

    public function allows(string $address): bool
    {
        return in_array($this->domainOf($address), $this->domains, true);
    }

    public function isForwarded(string $address): bool
    {
        return isset($this->forwarded[$this->domainOf($address) ?? '']);
    }

    /**
     * The address the mail node receives a list's mail on: the forwarding
     * target for a forwarded domain, the address itself otherwise.
     */
    public function nodeAddress(string $address): string
    {
        $address = trim($address);
        $target = $this->forwarded[$this->domainOf($address) ?? ''] ?? null;

        return $target === null ? $address : substr($address, 0, strrpos($address, '@') + 1) . $target;
    }

    private function domainOf(string $address): ?string
    {
        $address = trim($address);
        $at = strrpos($address, '@');

        return $at === false ? null : strtolower(substr($address, $at + 1));
    }
}
