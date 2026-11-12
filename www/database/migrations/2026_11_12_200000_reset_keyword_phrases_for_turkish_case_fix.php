<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Turkce "İ"/"I" kucultme hatasiyla ("için" -> "çin", "MİMARLIK" ->
 * "mimarlik") cikarilmis sayfa adaylari bir sonraki site taramasinda
 * yeniden uretilsin diye bayatlatilir. Mevcut veri silinmez; tarama
 * sayfa sayfa uzerine yazar.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sitemap_urls')
            ->whereNotNull('keyword_phrases_checked_at')
            ->update(['keyword_phrases_checked_at' => null]);
    }

    public function down(): void
    {
    }
};
