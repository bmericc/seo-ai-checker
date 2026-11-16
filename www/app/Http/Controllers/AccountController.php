<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Auth\AccountDeleter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Kullanicinin kendi hesabini silmesi. Onay bekleyen ve e-postasini
 * dogrulamamis kullanicilar da erisebilir (bkz. EnsureUserApproved);
 * mobil uygulama bu sayfayi WebView'inda acabilir.
 */
class AccountController extends Controller
{
    public function confirmDelete(Request $request, AccountDeleter $deleter): View
    {
        return view('account-delete', [
            'canDelete' => $deleter->canDelete($request->user()),
        ]);
    }

    public function destroy(Request $request, AccountDeleter $deleter): RedirectResponse
    {
        $user = $request->user();

        abort_unless($deleter->canDelete($user), 403, __('Tek admin olduğunuz için hesabınızı silemezsiniz. Önce başka bir kullanıcıyı admin yapın.'));

        if (strtolower(trim((string) $request->input('email'))) !== strtolower((string) $user->email)) {
            throw ValidationException::withMessages([
                'email' => __('Onaylamak için hesabınızın e-posta adresini yazın.'),
            ]);
        }

        Auth::logout();
        $deleter->delete($user);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('flash', ['type' => 'success', 'message' => __('Hesabınız ve tüm verileriniz silindi.')]);
    }
}
