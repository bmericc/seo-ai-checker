<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->json('lighthouse_raw')->nullable()->after('lighthouse_error');
        });
    }

    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn('lighthouse_raw');
        });
    }
};
