<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ai_crawlers (robots.txt AI crawler erisimi) bir keyword/URL ozelligi degil,
 * domain seviyesinde bir veri - domain_checks tablosuna tasindi
 * (bkz. 2026_07_17_134129_create_domain_checks_table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn('ai_crawlers');
        });
    }

    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->json('ai_crawlers')->nullable()->after('lighthouse_raw');
        });
    }
};
