<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompt_explorations', function (Blueprint $table) {
            // check_ai_answer bir anahtar kelime icin calistiginda sonucu o
            // kelimenin gecmisine baglar; kelime silinirse kayit domain'de kalir.
            $table->foreignId('keyword_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prompt_explorations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('keyword_id');
        });
    }
};
