<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tum domainler icin gecerli, admin'in yonettigi "rakip olamaz"
        // listesi (facebook.com, youtube.com gibi her SERP'te cikan devler).
        Schema::create('competitor_blacklist', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('competitor_blacklist')->insert(array_map(
            fn (string $domain) => ['domain' => $domain, 'created_at' => $now, 'updated_at' => $now],
            ['facebook.com', 'google.com', 'youtube.com'],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_blacklist');
    }
};
