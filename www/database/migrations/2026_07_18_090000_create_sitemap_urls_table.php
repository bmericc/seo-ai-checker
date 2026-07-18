<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sitemap.xml'de kesfedilen URL'lerin gecmisini tutar. Her domain kontrolunde
 * sitemap yeniden okunur; hala listede olan URL'lerin last_seen_at'i
 * guncellenir, artik listede olmayanlar removed_at ile isaretlenir (silinmez,
 * gecmis korunur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sitemap_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['domain_id', 'url'], 'sitemap_urls_domain_url_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitemap_urls');
    }
};
