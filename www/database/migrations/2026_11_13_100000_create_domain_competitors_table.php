<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            // Normalize edilmis host (www'siz, kucuk harf - bkz. App\Support\Domain::normalize).
            $table->string('competitor_domain');
            // "tracked" (gercek rakip, capraz analize girer) ya da "ignored"
            // (SERP kesfinden gizlenir) - bkz. DomainCompetitor::STATUSES.
            $table->string('status', 10);
            // "serp" (kesiften secildi) ya da "manual" (elle eklendi).
            $table->string('source', 10);
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Rakibin one ciktigi anahtar kelimeler (ham liste; takip
            // edilenlerle fark sayfa acilirken hesaplanir).
            $table->json('keywords')->nullable();
            // "dataforseo" (gercek siralama) ya da "crawl" (site taramasi).
            $table->string('keywords_source', 12)->nullable();
            $table->timestamp('keywords_fetched_at')->nullable();
            $table->timestamp('keywords_requested_at')->nullable();
            $table->text('keywords_error')->nullable();
            $table->timestamps();

            $table->unique(['domain_id', 'competitor_domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_competitors');
    }
};
