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
        Schema::table('domain_checks', function (Blueprint $table) {
            $table->json('gsc')->nullable()->after('crux');
            $table->json('ga4')->nullable()->after('gsc');
            $table->json('bing_backlinks')->nullable()->after('ga4');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domain_checks', function (Blueprint $table) {
            $table->dropColumn(['gsc', 'ga4', 'bing_backlinks']);
        });
    }
};
