<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('domain_facts', function (Blueprint $table) {
            $table->id();
            // Domain string - Domain kaydinin id'sine degil, domain STRING'ine
            // baglidir; ayni domain'i takip eden birden fazla Domain satiri
            // (farkli kullanicilar) bu TEK satiri paylasir. Bkz. Domain::fact().
            $table->string('domain')->unique();

            $table->string('whois_registrar')->nullable();
            $table->date('whois_registered_at')->nullable();
            $table->date('whois_expires_at')->nullable();
            $table->json('whois_raw')->nullable();
            $table->text('whois_error')->nullable();
            $table->timestamp('whois_checked_at')->nullable();

            $table->json('ai_crawlers')->nullable();
            $table->json('llms_txt')->nullable();
            $table->json('security_headers')->nullable();
            $table->json('canonical_host')->nullable();
            $table->json('crux')->nullable();
            // ai_crawlers/llms_txt/security_headers/canonical_host/crux'in en
            // son ne zaman gercekten taze tarandigi - bkz. DomainCheckRunner.
            $table->timestamp('checked_at')->nullable();

            $table->timestamps();
        });

        $this->backfillFromExistingData();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_facts');
    }

    /**
     * Eskiden bu bilgiler her Domain satirinin kendi kopyasinda tutuluyordu
     * (domains.whois_* ve en son DomainCheck'in ai_crawlers/llms_txt/
     * security_headers/canonical_host/crux alanlari). Ayni domain string'ini
     * birden fazla kullanici takip ediyorsa, en guncel/en dolu olan veriyi
     * tek bir domain_facts satirina tasiyoruz - hangi kullanicinin
     * kaydindan geldigi onemli degil.
     */
    private function backfillFromExistingData(): void
    {
        $domains = DB::table('domains')->select('domain')->distinct()->pluck('domain');

        foreach ($domains as $domainString) {
            $whois = DB::table('domains')
                ->where('domain', $domainString)
                ->orderByDesc('whois_checked_at')
                ->first(['whois_registrar', 'whois_registered_at', 'whois_expires_at', 'whois_raw', 'whois_error', 'whois_checked_at']);

            $latestCheck = DB::table('domain_checks')
                ->join('domains', 'domains.id', '=', 'domain_checks.domain_id')
                ->where('domains.domain', $domainString)
                ->orderByDesc('domain_checks.created_at')
                ->orderByDesc('domain_checks.id')
                ->first(['domain_checks.ai_crawlers', 'domain_checks.llms_txt', 'domain_checks.security_headers', 'domain_checks.canonical_host', 'domain_checks.crux', 'domain_checks.created_at']);

            DB::table('domain_facts')->insert([
                'domain' => $domainString,
                'whois_registrar' => $whois?->whois_registrar,
                'whois_registered_at' => $whois?->whois_registered_at,
                'whois_expires_at' => $whois?->whois_expires_at,
                'whois_raw' => $whois?->whois_raw,
                'whois_error' => $whois?->whois_error,
                'whois_checked_at' => $whois?->whois_checked_at,
                'ai_crawlers' => $latestCheck?->ai_crawlers,
                'llms_txt' => $latestCheck?->llms_txt,
                'security_headers' => $latestCheck?->security_headers,
                'canonical_host' => $latestCheck?->canonical_host,
                'crux' => $latestCheck?->crux,
                'checked_at' => $latestCheck?->created_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
