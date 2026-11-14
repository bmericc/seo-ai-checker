<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\VerifiedUserOnboarding;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sifreyle kayit olanlarin e-posta dogrulamasi. ADMIN_EMAILS yukseltmesi
 * ve bekleyen domain paylasimlarinin hesaba baglanmasi ancak burada,
 * e-posta sahipligi kanitlandiktan sonra yapilir.
 */
class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect('/');
        }

        return view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request, VerifiedUserOnboarding $onboarding): RedirectResponse
    {
        $request->fulfill();
        $user = $request->user()->fresh();
        $onboarding->handle($user);

        // Onay bekleyen kullanicida '/' tekrar yonlendirecegi icin mesaj
        // kaybolmasin diye dogrudan hedef sayfaya gonderilir.
        return redirect($user->isApproved() ? '/' : route('pending-approval'))->with('flash', [
            'type' => 'success',
            'message' => __('E-posta adresiniz doğrulandı.'),
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect('/');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('flash', [
            'type' => 'success',
            'message' => __('Doğrulama bağlantısı tekrar gönderildi.'),
        ]);
    }
}
