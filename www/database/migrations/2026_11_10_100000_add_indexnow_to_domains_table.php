<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            // Bkz. App\Services\IndexNow\IndexNowService - sitenin kokunde
            // "{key}.txt" olarak yayinlanmasi gereken anahtar. Ilk kullanimda
            // uretilir; degistirilirse eski dosya gecersiz olur.
            $table->string('indexnow_key', 64)->nullable()->after('ga4_property_id');
            $table->timestamp('indexnow_verified_at')->nullable()->after('indexnow_key');
        });

        Schema::create('indexnow_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);
            $table->unsignedInteger('url_count');
            $table->json('urls');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('ok')->default(false);
            $table->string('message', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indexnow_submissions');

        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['indexnow_key', 'indexnow_verified_at']);
        });
    }
};
