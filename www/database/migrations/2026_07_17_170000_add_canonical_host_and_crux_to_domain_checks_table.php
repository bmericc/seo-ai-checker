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
            $table->json('canonical_host')->nullable()->after('security_headers');
            $table->json('crux')->nullable()->after('canonical_host');
        });
    }

    public function down(): void
    {
        Schema::table('domain_checks', function (Blueprint $table) {
            $table->dropColumn(['canonical_host', 'crux']);
        });
    }
};
