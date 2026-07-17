<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domain seviyesinde, belirli bir anahtar kelime/URL'e bagli olmayan
 * kontrollerin (robots.txt AI crawler erisimi ve ilerideki benzer
 * site-genel araclar) gecmisini tutar - keyword bazli "checks" tablosundan
 * ayri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete();

            $table->json('ai_crawlers')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_checks');
    }
};
