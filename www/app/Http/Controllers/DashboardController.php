<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Domain;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $domains = Domain::query()
            ->withCount('keywords')
            ->orderBy('domain')
            ->get();

        return view('dashboard', ['domains' => $domains]);
    }
}
