<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mobile\ChangeFeed;
use App\Mobile\DomainScreen;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobil uygulamanin salt okunur ekranlari. Uye kendi domainlerini ve
 * kendisiyle paylasilanlari, admin tum domainleri gorur (web paneli ve
 * MCP ile ayni kural: Domain::accessibleBy).
 */
class DomainController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $domains = Domain::query()
            ->accessibleBy($user)
            ->with(['user', 'fact', 'latestDomainCheck', 'keywords', 'members' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderBy('domain')
            ->get();

        return response()->json([
            'domains' => $domains->map(fn (Domain $domain) => DomainScreen::summary($domain, $user))->values(),
            'web_url' => url('/'),
        ]);
    }

    public function screen(Request $request, int $domain): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $model = Domain::query()->accessibleBy($user)->with('members')->find($domain);

        if ($model === null) {
            return response()->json([
                'error' => 'not_found',
                'message' => __('Domain bulunamadı.'),
            ], 404);
        }

        return response()->json(DomainScreen::for($model, $user, (string) $request->query('tab', 'overview')));
    }

    public function changes(Request $request, ChangeFeed $feed): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $domains = Domain::query()->accessibleBy($user)->orderBy('domain')->get();

        return response()->json($feed->for($domains, (int) $request->query('days', '7')));
    }
}
