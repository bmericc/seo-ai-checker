<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Her DataForSEO backlink cekimi bir satir: ozet sayilar ayri
        // kolonlarda (trend grafigi icin), listeler JSON. Hatali cekimler de
        // satir olarak kalir (error dolu, veri bos) - sayfa son basarili
        // satiri gosterir, daha yeni bir hata varsa uyari verir.
        Schema::create('backlink_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('backlinks')->nullable();
            $table->unsignedInteger('referring_domains')->nullable();
            $table->unsignedInteger('referring_domains_nofollow')->nullable();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->unsignedTinyInteger('spam_score')->nullable();
            $table->json('summary')->nullable();
            $table->json('top_referring_domains')->nullable();
            $table->json('anchors')->nullable();
            $table->json('new_referring_domains')->nullable();
            $table->json('lost_referring_domains')->nullable();
            $table->decimal('cost', 8, 4)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['domain_id', 'fetched_at']);
        });

        Schema::table('domains', function (Blueprint $table) {
            // Kuyruga birakilan ama henuz islenmemis yenileme (bkz. FetchDomainBacklinks).
            $table->timestamp('backlinks_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('backlinks_requested_at');
        });

        Schema::dropIfExists('backlink_snapshots');
    }
};
