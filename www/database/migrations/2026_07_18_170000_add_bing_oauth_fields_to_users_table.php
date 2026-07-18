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
        Schema::table('users', function (Blueprint $table) {
            $table->text('bing_access_token')->nullable()->after('google_token_expires_at');
            $table->text('bing_refresh_token')->nullable()->after('bing_access_token');
            $table->timestamp('bing_token_expires_at')->nullable()->after('bing_refresh_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bing_access_token', 'bing_refresh_token', 'bing_token_expires_at']);
        });
    }
};
