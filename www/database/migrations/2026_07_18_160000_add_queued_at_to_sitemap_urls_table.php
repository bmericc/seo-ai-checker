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
        Schema::table('sitemap_urls', function (Blueprint $table) {
            $table->timestamp('lighthouse_queued_at')->nullable()->after('lighthouse_checked_at');
            $table->timestamp('onpage_queued_at')->nullable()->after('onpage_checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sitemap_urls', function (Blueprint $table) {
            $table->dropColumn(['lighthouse_queued_at', 'onpage_queued_at']);
        });
    }
};
