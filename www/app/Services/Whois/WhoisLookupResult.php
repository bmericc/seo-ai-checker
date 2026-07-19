<?php

declare(strict_types=1);

namespace App\Services\Whois;

final class WhoisLookupResult
{
    /**
     * @param  list<string>  $nameServers
     * @param  list<string>  $statuses
     * @param  array<string, mixed>  $raw  Paketten donen tum WHOIS/RDAP alanlari - gorunmeyen skorlanmis kolonlarin (registrar/registered_at/expires_at) disinda ne gelirse gelsin kaybolmasin diye oldugu gibi saklanir.
     */
    public function __construct(
        public readonly bool $found,
        public readonly ?string $registrar = null,
        public readonly ?string $registeredAt = null,
        public readonly ?string $expiresAt = null,
        public readonly array $nameServers = [],
        public readonly array $statuses = [],
        public readonly array $raw = [],
        public readonly ?string $error = null,
    ) {
    }
}
