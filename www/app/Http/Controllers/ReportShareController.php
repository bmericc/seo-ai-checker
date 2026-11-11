<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Check;
use App\Models\Domain;
use App\Models\ReportShare;
use App\Services\Analytics\ScoreHistoryBuilder;
use App\Services\Report\ReportShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ReportShareController extends Controller
{
    public function store(Request $request, Domain $domain, ReportShareService $shares): RedirectResponse
    {
        $this->ensureCanEditDomain($request, $domain);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['string', Rule::in(ReportShare::SECTIONS)],
            'expires_in' => ['nullable', 'integer', Rule::in(array_filter(ReportShare::EXPIRY_OPTIONS))],
        ]);

        $share = $shares->create(
            $domain,
            $request->user(),
            $data['sections'],
            isset($data['expires_in']) ? (int) $data['expires_in'] : null,
            $data['title'] ?? null,
        );

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('Rapor linki oluşturuldu: :url', ['url' => $share->url()])])
            ->with('open_share_modal', true);
    }

    public function destroy(Request $request, Domain $domain, ReportShare $share): RedirectResponse
    {
        $this->ensureCanEditDomain($request, $domain);
        abort_unless($share->domain_id === $domain->id, 404);

        $share->update(['revoked_at' => now()]);

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('Rapor linki kapatıldı.')])
            ->with('open_share_modal', true);
    }

    /**
     * Giris gerektirmeyen rapor sayfasi.
     */
    public function show(string $token, ScoreHistoryBuilder $scoreHistoryBuilder): Response
    {
        $share = ReportShare::query()->where('token', $token)->first();

        if ($share === null || !$share->isActive()) {
            return response()->view('reports.unavailable', [], $share === null ? 404 : 410)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $share->forceFill([
            'view_count' => $share->view_count + 1,
            'last_viewed_at' => now(),
        ])->saveQuietly();

        $domain = $share->domain()->with([
            'keywords' => fn ($q) => $q->orderBy('keyword'),
            'keywords.latestCheck',
            'latestDomainCheck',
            'fact',
        ])->firstOrFail();

        $keywordChecks = Check::query()
            ->whereIn('keyword_id', $domain->keywords->pluck('id'))
            ->select([
                'id', 'created_at',
                'ai_overview_present', 'ai_overview_target_cited',
                'lighthouse_performance', 'lighthouse_seo', 'lighthouse_accessibility', 'lighthouse_best_practices',
                'target_position',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $domainCheckHistory = $scoreHistoryBuilder->domainCheckHistory(
            $domain->domainChecks()
                ->reorder()
                ->select(['id', 'domain_id', 'created_at', 'crux', 'gsc', 'ga4'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
        );

        return response()->view('reports.public', [
            'share' => $share,
            'domain' => $domain,
            'scoreHistory' => $scoreHistoryBuilder->groupedByDay($keywordChecks),
            'domainCheckHistory' => $domainCheckHistory,
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
