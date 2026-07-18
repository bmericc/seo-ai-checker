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
            $table->json('suggested_keywords')->nullable()->after('crux');
        });
    }

    public function down(): void
    {
        Schema::table('domain_checks', function (Blueprint $table) {
            $table->dropColumn('suggested_keywords');
        });
    }
};
