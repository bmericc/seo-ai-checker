<?php

declare(strict_types=1);

namespace App\Mcp;

use Laravel\Mcp\Server\Registrar;

/**
 * MCP icin OAuth scope'lari. "read" verileri okur; "write" domain/keyword
 * ekler, kontrol baslatir, IndexNow gonderir, rapor linki uretir.
 *
 * laravel/mcp kendi "mcp:use" scope'unu da Passport'a kaydeder; yalnizca
 * onu isteyen bir istemciye okuma yetkisi verilir.
 */
final class McpScopes
{
    public const READ = 'read';

    public const WRITE = 'write';

    /**
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return [
            self::READ => __('Domainlerinizi, anahtar kelimelerinizi ve kontrol sonuçlarını okuma'),
            self::WRITE => __('Domain ve anahtar kelime ekleme, kontrol başlatma, IndexNow gönderme, rapor linki oluşturma'),
            Registrar::OAUTH_SCOPE => __('MCP sunucusunu kullanma (yalnızca okuma)'),
        ];
    }

    /**
     * Istemcilere metadata'da ilan edilen scope'lar: yalnizca paketin
     * "mcp:use" scope'u (ChatGPT'de calisan mail-mcp ile birebir ayni).
     * read/write da ilan edildiginde ChatGPT kesiften sonra istemci kaydina
     * gecmeden "Some app settings were rejected" diyordu. read/write
     * Passport'ta tanimli kalir; acikca isteyen istemci alir, digerleri
     * onay ekranindaki "Yazma izni de ver" kutusuyla yazma izni verir.
     *
     * @return list<string>
     */
    public static function supported(): array
    {
        return [Registrar::OAUTH_SCOPE];
    }
}
