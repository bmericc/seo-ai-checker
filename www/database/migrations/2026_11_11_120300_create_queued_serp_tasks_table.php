<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queued_serp_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            // Ayni haftalik calistirmaya ait gorevleri gruplar.
            $table->string('batch', 40)->index();
            $table->string('device', 10);
            $table->string('task_id')->nullable();
            $table->json('result')->nullable();
            $table->string('error', 1000)->nullable();
            $table->timestamp('completed_at')->nullable();
            // Kelimenin tum cihaz sonuclari toplanip kontrol job'i kuyruga
            // birakildiginda doldurulur (ayni sonucun iki kez kullanilmamasi icin).
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['completed_at', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queued_serp_tasks');
    }
};
