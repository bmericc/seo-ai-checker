<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /**
     * Dil secimini kaydeder: giris yapmis kullanicida hesaba, herkeste ayrica
     * bir yillik cookie'ye (cikis yaptiktan sonra da ayni dil kalsin diye).
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(User::SUPPORTED_LOCALES)],
        ]);

        $request->user()?->forceFill(['locale' => $validated['locale']])->save();

        return back()->withCookie(cookie()->forever(SetLocale::COOKIE, $validated['locale']));
    }
}
