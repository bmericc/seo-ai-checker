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
        Schema::table('checks', function (Blueprint $table) {
            // Bkz. App\Services\Eeat\EeatScorer - onpage analizi basarisiz
            // olduysa (onpage_error doluysa) her ikisi de null kalir.
            $table->unsignedTinyInteger('eeat_score')->nullable()->after('onpage_error');
            $table->json('eeat_breakdown')->nullable()->after('eeat_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn(['eeat_score', 'eeat_breakdown']);
        });
    }
};
