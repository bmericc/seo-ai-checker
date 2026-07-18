<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Keyword;
use App\Services\Analytics\ScoreHistoryBuilder;
use App\Services\CheckRunner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KeywordController extends Controller
{
    public function store(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanAccessDomain($request, $domain);

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

        $ascendingChecks = $keyword->checks()->reorder()->orderBy('created_at')->orderBy('id')->get();
        $scoreHistory = $scoreHistoryBuilder->groupedByDay($ascendingChecks);

        return view('keywords.show', ['keyword' => $keyword, 'scoreHistory' => $scoreHistory]);
    }

    public function check(Request $request, Keyword $keyword, CheckRunner $checkRunner): RedirectResponse
    {
        $keyword->load('domain');
        $this->ensureCanAccessKeyword($request, $keyword);

        $checkRunner->run($keyword);

        return redirect()
            ->route('keywords.show', $keyword)
            ->with('flash', ['type' => 'success', 'message' => __('Kontrol tamamlandı.')]);
    }

    public function destroy(Request $request, Keyword $keyword): RedirectResponse
    {
        $keyword->loadMissing('domain');
        $this->ensureCanAccessKeyword($request, $keyword);

        $domain = $keyword->domain;
        $keyword->delete();

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('Anahtar kelime silindi.')]);
    }
}
