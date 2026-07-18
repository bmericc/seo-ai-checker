<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sitemap_urls', function (Blueprint $table) {
            $table->unsignedTinyInteger('lighthouse_performance')->nullable();
            $table->unsignedTinyInteger('lighthouse_seo')->nullable();
            $table->unsignedTinyInteger('lighthouse_accessibility')->nullable();
            $table->unsignedTinyInteger('lighthouse_best_practices')->nullable();
            $table->json('lighthouse_raw')->nullable();
            $table->string('lighthouse_error')->nullable();
            $table->timestamp('lighthouse_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sitemap_urls', function (Blueprint $table) {
            $table->dropColumn([
                'lighthouse_performance',
                'lighthouse_seo',
                'lighthouse_accessibility',
                'lighthouse_best_practices',
                'lighthouse_raw',
                'lighthouse_error',
                'lighthouse_checked_at',
            ]);
        });
    }
};
