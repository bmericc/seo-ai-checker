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

    public function show(Keyword $keyword): View
    {
        $keyword->load(['domain', 'checks']);

        return view('keywords.show', ['keyword' => $keyword]);
    }

    public function check(Keyword $keyword, CheckRunner $checkRunner): RedirectResponse
    {
        $keyword->load('domain');
        $checkRunner->run($keyword);

        return redirect()
            ->route('keywords.show', $keyword)
            ->with('flash', ['type' => 'success', 'message' => 'Kontrol tamamlandi.']);
    }

    public function destroy(Keyword $keyword): RedirectResponse
    {
        $domain = $keyword->domain;
        $keyword->delete();

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => 'Anahtar kelime silindi.']);
    }
}
