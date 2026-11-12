<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site geneli anahtar kelime onerisi: her sitemap URL'inin aday ifadeleri
 * (CrawlSiteKeywordCandidates ya da on-page taramasi doldurur) ve domain
 * seviyesinde birlestirilmis oneri listesi + arka plan taramasinin ilerlemesi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sitemap_urls', function (Blueprint $table) {
            $table->json('keyword_phrases')->nullable();
            $table->timestamp('keyword_phrases_checked_at')->nullable();
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->json('site_keyword_suggestions')->nullable();
            $table->json('site_keyword_scan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sitemap_urls', function (Blueprint $table) {
            $table->dropColumn(['keyword_phrases', 'keyword_phrases_checked_at']);
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['site_keyword_suggestions', 'site_keyword_scan']);
        });
    }
};
