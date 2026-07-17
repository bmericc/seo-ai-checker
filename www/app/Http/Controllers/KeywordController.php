<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Keyword;
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
            ->with('flash', ['type' => 'success', 'message' => sprintf('"%s" eklendi.', trim($data['keyword']))]);
    }

    public function show(Request $request, Keyword $keyword): View
    {
        $keyword->loadMissing('domain');
        $this->ensureCanAccessKeyword($request, $keyword);

        $keyword->load(['domain', 'checks']);

        return view('keywords.show', ['keyword' => $keyword]);
    }

    public function check(Request $request, Keyword $keyword, CheckRunner $checkRunner): RedirectResponse
    {
        $keyword->load('domain');
        $this->ensureCanAccessKeyword($request, $keyword);

        $checkRunner->run($keyword);

        return redirect()
            ->route('keywords.show', $keyword)
            ->with('flash', ['type' => 'success', 'message' => 'Kontrol tamamlandi.']);
    }

    public function destroy(Request $request, Keyword $keyword): RedirectResponse
    {
        $keyword->loadMissing('domain');
        $this->ensureCanAccessKeyword($request, $keyword);

        $domain = $keyword->domain;
        $keyword->delete();

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => 'Anahtar kelime silindi.']);
    }
}
