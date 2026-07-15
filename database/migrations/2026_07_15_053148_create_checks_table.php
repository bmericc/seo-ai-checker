<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained('keywords')->cascadeOnDelete();

            $table->boolean('blocked')->default(false);
            $table->text('block_reason')->nullable();
            $table->unsignedInteger('target_position')->nullable();
            $table->json('organic_results')->nullable();

            $table->boolean('ai_overview_present')->default(false);
            $table->json('ai_overview_cited_domains')->nullable();
            $table->boolean('ai_overview_target_cited')->nullable();
            $table->text('ai_overview_note')->nullable();

            $table->json('onpage')->nullable();
            $table->text('onpage_error')->nullable();

            $table->unsignedTinyInteger('lighthouse_performance')->nullable();
            $table->unsignedTinyInteger('lighthouse_seo')->nullable();
            $table->unsignedTinyInteger('lighthouse_accessibility')->nullable();
            $table->unsignedTinyInteger('lighthouse_best_practices')->nullable();
            $table->text('lighthouse_error')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checks');
    }
};
