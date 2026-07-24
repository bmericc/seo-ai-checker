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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('whois_registrar')->nullable();
            $table->date('whois_registered_at')->nullable();
            $table->date('whois_expires_at')->nullable();
            $table->json('whois_raw')->nullable();
            $table->text('whois_error')->nullable();
            $table->timestamp('whois_checked_at')->nullable();
        });
    }
};
