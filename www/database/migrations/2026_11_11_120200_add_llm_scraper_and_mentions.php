<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->boolean('llm_scraper_enabled')->default(false)->after('llm_visibility_enabled');
        });

        Schema::table('domain_facts', function (Blueprint $table) {
            $table->json('llm_mentions')->nullable();
            $table->timestamp('llm_mentions_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('llm_scraper_enabled');
        });

        Schema::table('domain_facts', function (Blueprint $table) {
            $table->dropColumn(['llm_mentions', 'llm_mentions_checked_at']);
        });
    }
};
