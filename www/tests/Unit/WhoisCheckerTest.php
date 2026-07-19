<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Whois\WhoisChecker;
use BahriCanli\DomainHunter\DomainParser;
use BahriCanli\DomainHunter\WhoisResult;
use BahriCanli\DomainHunter\WhoisService;
use PHPUnit\Framework\TestCase;

class WhoisCheckerTest extends TestCase
{
    /**
     * WhoisService talks to real WHOIS/RDAP servers over the network
     * (fsockopen/curl), so lookup() is stubbed via a subclass instead of
     * hitting them in tests - compoundTlds() (a pure in-memory list, used
     * by DomainParser for e.g. "example.com.tr") stays real.
     */
    private function checkerReturning(?WhoisResult $result, ?\Throwable $throw = null): WhoisChecker
    {
        $whois = new class($result, $throw) extends WhoisService
        {
            public function __construct(private readonly ?WhoisResult $result, private readonly ?\Throwable $throw)
            {
            }

            public function lookup(string $label, string $tld): ?WhoisResult
            {
                if ($this->throw !== null) {
                    throw $this->throw;
                }

                return $this->result;
            }
        };

        return new WhoisChecker(new DomainParser($whois), $whois);
    }

    public function test_returns_whois_data_for_a_registered_domain(): void
    {
        $whoisResult = new WhoisResult();
        $whoisResult->registrar = 'Example Registrar Inc.';
        $whoisResult->creationDate = '2010-05-01';
        $whoisResult->expirationDate = '2027-05-01';
        $whoisResult->nameServers = ['ns1.example.com', 'ns2.example.com'];
        $whoisResult->statuses = ['clientTransferProhibited'];

        $result = $this->checkerReturning($whoisResult)->check('example.com');

        $this->assertTrue($result->found);
        $this->assertNull($result->error);
        $this->assertSame('Example Registrar Inc.', $result->registrar);
        $this->assertSame('2010-05-01', $result->registeredAt);
        $this->assertSame('2027-05-01', $result->expiresAt);
        $this->assertSame(['ns1.example.com', 'ns2.example.com'], $result->nameServers);
        $this->assertSame('Example Registrar Inc.', $result->raw['registrar']);
    }

    public function test_domain_that_is_available_is_reported_as_not_found_with_an_explanation(): void
    {
        $result = $this->checkerReturning(null)->check('example.com');

        $this->assertFalse($result->found);
        $this->assertNotNull($result->error);
        $this->assertNull($result->registrar);
    }

    public function test_unsupported_tld_error_is_surfaced_without_throwing(): void
    {
        $result = $this->checkerReturning(null, new \InvalidArgumentException('TLD .zzz is not supported.'))
            ->check('example.zzz');

        $this->assertFalse($result->found);
        $this->assertSame('TLD .zzz is not supported.', $result->error);
    }

    public function test_unreachable_whois_server_error_is_surfaced_without_throwing(): void
    {
        $result = $this->checkerReturning(null, new \RuntimeException('Could not reach WHOIS server for .com.'))
            ->check('example.com');

        $this->assertFalse($result->found);
        $this->assertSame('Could not reach WHOIS server for .com.', $result->error);
    }

    public function test_malformed_domain_input_is_rejected_before_any_lookup(): void
    {
        $result = $this->checkerReturning(null)->check('not-a-domain');

        $this->assertFalse($result->found);
        $this->assertNotNull($result->error);
    }
}
