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
            $table->json('onpage_data')->nullable();
            $table->string('onpage_error')->nullable();
            $table->timestamp('onpage_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sitemap_urls', function (Blueprint $table) {
            $table->dropColumn(['onpage_data', 'onpage_error', 'onpage_checked_at']);
        });
    }
};
