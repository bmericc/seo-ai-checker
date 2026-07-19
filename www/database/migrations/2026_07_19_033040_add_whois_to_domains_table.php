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
        Schema::table('domains', function (Blueprint $table) {
            $table->string('whois_registrar')->nullable()->after('ga4_property_id');
            $table->date('whois_registered_at')->nullable()->after('whois_registrar');
            $table->date('whois_expires_at')->nullable()->after('whois_registered_at');
            $table->json('whois_raw')->nullable()->after('whois_expires_at');
            $table->text('whois_error')->nullable()->after('whois_raw');
            $table->timestamp('whois_checked_at')->nullable()->after('whois_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn([
                'whois_registrar',
                'whois_registered_at',
                'whois_expires_at',
                'whois_raw',
                'whois_error',
                'whois_checked_at',
            ]);
        });
    }
};
