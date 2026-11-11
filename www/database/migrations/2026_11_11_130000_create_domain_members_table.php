<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            // Davet edilen kisi henuz hic giris yapmadiysa null kalir; ilk
            // Google girisinde e-posta eslesmesiyle baglanir (bkz.
            // DomainMember::claimPendingFor).
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('email');
            // "read" ya da "write" (bkz. DomainMember::PERMISSIONS).
            $table->string('permission', 10);
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['domain_id', 'email']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_members');
    }
};
