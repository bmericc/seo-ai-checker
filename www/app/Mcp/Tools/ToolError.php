<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Kullaniciya gosterilecek tool hatasi. laravel/mcp yalnizca dogrulama ve
 * yetki istisnalarinin mesajini production'da oldugu gibi istemciye iletir
 * (digerleri genel bir mesaja donusur), bu yuzden AuthorizationException'dan
 * turetiyoruz.
 */
final class ToolError extends AuthorizationException
{
}
