<?php

declare(strict_types=1);

namespace App\Services\Whois;

use BahriCanli\DomainHunter\DomainParser;
use BahriCanli\DomainHunter\WhoisService;
use InvalidArgumentException;
use RuntimeException;

/**
 * bahricanli/domainhunter (WHOIS/RDAP lookup + domain-name parsing)
 * paketini sarmalar; domain kayit tarihi, registrar ve name server
 * bilgisini App\Jobs\RunDomainWhoisLookup icin tek bir sonuc nesnesine
 * cevirir. WHOIS sorgulari (TCP port 43'e dogrudan baglanti ya da RDAP
 * HTTP istegi) yavas/guvenilmez olabildigi icin bu servis her zaman
 * kuyruklu bir is icinden cagrilir, hicbir web istegi icinde dogrudan
 * calistirilmaz.
 */
final class WhoisChecker
{
    public function __construct(
        private readonly DomainParser $parser,
        private readonly WhoisService $whois,
    ) {
    }

    public function check(string $domain): WhoisLookupResult
    {
        try {
            ['label' => $label, 'tld' => $tld] = $this->parser->parse($domain);
        } catch (InvalidArgumentException $e) {
            return new WhoisLookupResult(found: false, error: $e->getMessage());
        }

        try {
            $result = $this->whois->lookup($label, $tld);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return new WhoisLookupResult(found: false, error: $e->getMessage());
        }

        if ($result === null) {
            return new WhoisLookupResult(found: false, error: 'Domain kayıtlı görünmüyor (WHOIS sunucusu "müsait" yanıtı verdi).');
        }

        return new WhoisLookupResult(
            found: true,
            registrar: $result->registrar,
            registeredAt: $result->creationDate,
            expiresAt: $result->expirationDate,
            nameServers: $result->nameServers,
            statuses: $result->statuses,
            raw: [
                'domain_name' => $result->domainName,
                'registrar' => $result->registrar,
                'whois_server' => $result->whoisServer,
                'referral_url' => $result->referralUrl,
                'name_servers' => $result->nameServers,
                'statuses' => $result->statuses,
                'creation_date' => $result->creationDate,
                'updated_date' => $result->updatedDate,
                'expiration_date' => $result->expirationDate,
            ],
        );
    }
}
