<?php

use Illuminate\Database\Migrations\Migration;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

return new class extends Migration
{
    public function up(): void
    {
        // Mobil uygulama, tarayicida tamamlanan giristen sonra tek
        // kullanimlik kodu bir Passport kisisel erisim token'ina cevirir
        // (bkz. Api\V1\AuthController). Passport bunun icin "personal
        // access" yetkisi olan bir istemci ister; kurulumda elle
        // "passport:client --personal" calistirmak gerekmesin diye
        // burada olusturulur.
        $exists = Client::query()
            ->where('revoked', false)
            ->get()
            ->contains(fn (Client $client) => $client->hasGrantType('personal_access'));

        if (!$exists) {
            app(ClientRepository::class)->createPersonalAccessGrantClient('Seo Checker mobile', 'users');
        }
    }

    public function down(): void
    {
        Client::query()->where('name', 'Seo Checker mobile')->delete();
    }
};
