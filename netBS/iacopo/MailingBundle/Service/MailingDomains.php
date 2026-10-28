<?php

declare(strict_types=1);

namespace Iacopo\MailingBundle\Service;

/**
 * The domains the mail node relays (`relay_domains` in the mailserver repo's
 * main.cf, minus the SRS one). A list address on any other domain would be
 * exported but never receive mail, so the UI refuses it and the export skips it.
 */
class MailingDomains
{
    /** @var list<string> */
    private readonly array $domains;

    /**
     * @param list<string> $domains from MAILING_DOMAINS, comma-separated
     */
    public function __construct(array $domains)
    {
        $this->domains = array_values(array_filter(array_map(
            static fn ($domain): string => strtolower(trim((string) $domain)),
            $domains,
        )));
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
        $address = trim($address);
        $at = strrpos($address, '@');

        return $at !== false && in_array(strtolower(substr($address, $at + 1)), $this->domains, true);
    }
}
