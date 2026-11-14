<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * E-posta + sifre girisiyle birlikte e-posta dogrulamasi zorunlu oldu.
 * O zamana kadar tek giris yolu Google'di (e-postayi Google dogrular) ama
 * email_verified_at hic doldurulmuyordu; mevcut hesaplar kilitlenmesin
 * diye hepsi dogrulanmis sayilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        // Geri alinmaz: hangi kaydin bu migration'la doldugu bilinmiyor.
    }
};
