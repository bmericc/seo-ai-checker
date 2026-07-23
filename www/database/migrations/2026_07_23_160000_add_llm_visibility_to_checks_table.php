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
        Schema::table('checks', function (Blueprint $table) {
            // Saglayici basina {present, response, error} - bkz.
            // App\Services\Llm\LlmVisibilityResult. Domain'in
            // llm_visibility_enabled ve o saglayici icin bir API key'i yoksa
            // o saglayici hic bu diziye eklenmez (null degil, yok).
            $table->json('llm_visibility')->nullable()->after('ai_overview_note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn('llm_visibility');
        });
    }
};
