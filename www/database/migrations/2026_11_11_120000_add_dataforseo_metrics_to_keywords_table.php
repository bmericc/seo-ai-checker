<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->unsignedInteger('search_volume')->nullable()->after('url');
            $table->float('cpc')->nullable()->after('search_volume');
            $table->float('competition')->nullable()->after('cpc');
            $table->unsignedTinyInteger('keyword_difficulty')->nullable()->after('competition');
            $table->string('search_intent')->nullable()->after('keyword_difficulty');
            $table->json('monthly_searches')->nullable()->after('search_intent');
            $table->timestamp('metrics_fetched_at')->nullable()->after('monthly_searches');
            $table->string('metrics_error', 1000)->nullable()->after('metrics_fetched_at');
        });
    }

    public function down(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->dropColumn([
                'search_volume', 'cpc', 'competition', 'keyword_difficulty',
                'search_intent', 'monthly_searches', 'metrics_fetched_at', 'metrics_error',
            ]);
        });
    }
};
