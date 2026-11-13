<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CompetitorBlacklist;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('settings', [
            'competitorBlacklist' => $request->user()?->is_admin
                ? CompetitorBlacklist::query()->orderBy('domain')->get()
                : null,
        ]);
    }
}
