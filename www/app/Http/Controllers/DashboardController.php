<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Domain;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(): View
    {
        if (!Auth::check()) {
            return view('home');
        }

        $user = Auth::user();

        $domains = Domain::query()
            ->withCount('keywords')
            ->with('latestDomainCheck')
            ->when(!$user->is_admin, fn ($query) => $query->where('user_id', $user->id))
            ->orderBy('domain')
            ->get();

        return view('dashboard', ['domains' => $domains]);
    }
}
