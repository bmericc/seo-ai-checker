<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Domain basina, CrawlGraph (Common Crawl) arsivi basina tek satir.
        // Eklenen / kaybolan domain'ler bir onceki arsivin satiriyla
        // karsilastirilarak bizde hesaplanir (CrawlGraph /changes ayrica
        // kota yer, bu yuzden kullanilmaz).
        Schema::create('crawlgraph_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('release_id', 64);
            $table->string('release_label', 64)->nullable();
            $table->unsignedInteger('total_linking_domains')->nullable();
            $table->unsignedTinyInteger('authority')->nullable();
            $table->unsignedBigInteger('rank')->nullable();
            $table->json('linking_domains')->nullable();
            $table->json('added_domains')->nullable();
            $table->json('removed_domains')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['domain_id', 'release_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawlgraph_snapshots');
    }
};
