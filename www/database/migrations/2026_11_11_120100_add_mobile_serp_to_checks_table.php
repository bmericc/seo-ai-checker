<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->unsignedSmallInteger('mobile_target_position')->nullable()->after('organic_results');
            $table->json('mobile_organic_results')->nullable()->after('mobile_target_position');
            $table->boolean('mobile_blocked')->nullable()->after('mobile_organic_results');
            $table->string('mobile_block_reason', 1000)->nullable()->after('mobile_blocked');
            // "manual" = arayuz/MCP kontrolu (live SERP), "scheduled" = haftalik
            // otomatik kontrol (Standard kuyruk SERP).
            $table->string('source', 20)->default('manual')->after('keyword_id');
        });
    }

    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn(['mobile_target_position', 'mobile_organic_results', 'mobile_blocked', 'mobile_block_reason', 'source']);
        });
    }
};
