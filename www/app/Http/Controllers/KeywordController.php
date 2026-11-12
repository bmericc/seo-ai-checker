<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\RunKeywordCheck;
use App\Models\Domain;
use App\Models\Keyword;
use App\Services\Analytics\AiOverviewVolatility;
use App\Services\Analytics\ScoreHistoryBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KeywordController extends Controller
{
    public function store(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanEditDomain($request, $domain);

        $data = $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'],
        ]);

        $domain->keywords()->create([
            'keyword' => trim($data['keyword']),
            'url' => $data['url'] ?? null,
        ]);

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('":keyword" eklendi.', ['keyword' => trim($data['keyword'])])]);
    }

    public function show(Request $request, Keyword $keyword, ScoreHistoryBuilder $scoreHistoryBuilder): View
    {
        $keyword->loadMissing('domain');
        $this->ensureCanAccessKeyword($request, $keyword);

        $keyword->load(['domain', 'checks']);

        $ascendingChecks = $keyword->checks()
            ->reorder()
            ->select([
                'id', 'created_at',
                'ai_overview_present', 'ai_overview_target_cited',
                'lighthouse_performance', 'lighthouse_seo', 'lighthouse_accessibility', 'lighthouse_best_practices',
                'target_position',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $scoreHistory = $scoreHistoryBuilder->groupedByDay($ascendingChecks);

        return view('keywords.show', [
            'keyword' => $keyword,
            'scoreHistory' => $scoreHistory,
            'aioKeywordStability' => AiOverviewVolatility::forChecks($keyword->checks),
            'canEdit' => $keyword->domain->canBeEditedBy($request->user()),
        ]);
    }

    public function check(Request $request, Keyword $keyword): RedirectResponse
    {
        $keyword->load('domain');
        $this->ensureCanEditKeyword($request, $keyword);

        // Kontrol (SERP + AI Overview + LLM + Lighthouse) proxy/Cloudflare
        // timeout'unu asabildigi icin web isteginde senkron calistirilmaz.
        RunKeywordCheck::dispatch($keyword->id);

        return redirect()
            ->route('keywords.show', $keyword)
            ->with('flash', ['type' => 'success', 'message' => __('Kontrol kuyruğa alındı, birkaç dakika içinde sonuçlar güncellenecek.')]);
    }

    public function destroy(Request $request, Keyword $keyword): RedirectResponse
    {
        $keyword->loadMissing('domain');
        $this->ensureCanEditKeyword($request, $keyword);

        $domain = $keyword->domain;
        $keyword->delete();

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('Anahtar kelime silindi.')]);
    }
}
