<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Domain;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /** Android/iOS kabuk uygulamasinin WebView'i bu ifadeyi User-Agent'a ekler. */
    public const string MOBILE_APP_USER_AGENT = 'SeoAiCheckerApp/';

    public function index(Request $request): View|RedirectResponse
    {
        if (!Auth::check()) {
            // Uygulamada tanitim sayfasi yerine giris; uygulama /login'i
            // yakalayip kendi giris ekranini (sistem tarayicisi) acar.
            if (str_contains((string) $request->userAgent(), self::MOBILE_APP_USER_AGENT)) {
                return redirect()->route('login');
            }

            return view('home');
        }

        $user = Auth::user();

        $domains = Domain::query()
            ->withCount('keywords')
            ->with(['latestDomainCheck', 'fact', 'user', 'members' => fn ($query) => $query->where('user_id', $user->id)])
            ->accessibleBy($user)
            ->orderBy('domain')
            ->get();

        return view('dashboard', ['domains' => $domains]);
    }
}
