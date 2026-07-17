<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $fallbackUserId = DB::table('users')
            ->orderByDesc('is_admin')
            ->orderBy('id')
            ->value('id');

        if ($fallbackUserId !== null) {
            DB::table('domains')
                ->whereNull('user_id')
                ->update(['user_id' => $fallbackUserId]);
        }
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
