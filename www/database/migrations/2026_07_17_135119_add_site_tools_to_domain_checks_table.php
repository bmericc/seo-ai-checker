<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_checks', function (Blueprint $table) {
            $table->json('sitemap')->nullable()->after('ai_crawlers');
            $table->json('llms_txt')->nullable()->after('sitemap');
            $table->json('security_headers')->nullable()->after('llms_txt');
        });
    }

    public function down(): void
    {
        Schema::table('domain_checks', function (Blueprint $table) {
            $table->dropColumn(['sitemap', 'llms_txt', 'security_headers']);
        });
    }
};
