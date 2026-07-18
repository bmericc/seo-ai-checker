<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Services\DomainCheckRunner;
use App\Support\Domain as DomainHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['domain' => ['required', 'string']]);

        $domain = DomainHelper::fromFreeText($request->string('domain')->toString());

        if ($domain === null) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Gecerli bir domain girin (ornek: example.com).']);
        }

        $existing = Domain::query()
            ->where('domain', $domain)
            ->where('user_id', $request->user()->id)
            ->first();
        if ($existing !== null) {
            return redirect()
                ->route('domains.show', $existing)
                ->with('flash', ['type' => 'error', 'message' => sprintf('%s zaten kayitli.', $domain)]);
        }

        $record = $request->user()->domains()->create(['domain' => $domain]);

        return redirect()
            ->route('domains.show', $record)
            ->with('flash', ['type' => 'success', 'message' => sprintf('%s eklendi.', $domain)]);
    }

    private const MAX_SITEMAP_URLS_DISPLAYED = 200;

    public function show(Request $request, Domain $domain): View
    {
        $this->ensureCanAccessDomain($request, $domain);

        $domain->load([
            'keywords' => fn ($q) => $q->orderBy('keyword'),
            'keywords.latestCheck',
            'latestDomainCheck',
            'user',
        ]);

        $sitemapUrlCounts = [
            'active' => $domain->sitemapUrls()->whereNull('removed_at')->count(),
            'removed' => $domain->sitemapUrls()->whereNotNull('removed_at')->count(),
        ];

        $sitemapUrls = $domain->sitemapUrls()
            ->orderByRaw('removed_at IS NULL')
            ->orderByDesc('removed_at')
            ->orderByDesc('last_seen_at')
            ->limit(self::MAX_SITEMAP_URLS_DISPLAYED)
            ->get();

        return view('domains.show', [
            'domain' => $domain,
            'sitemapUrls' => $sitemapUrls,
            'sitemapUrlCounts' => $sitemapUrlCounts,
        ]);
    }

    public function check(Request $request, Domain $domain, DomainCheckRunner $checkRunner): RedirectResponse
    {
        $this->ensureCanAccessDomain($request, $domain);

        $checkRunner->run($domain);

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => 'Site kontrolu tamamlandi.']);
    }

    public function destroy(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanAccessDomain($request, $domain);

        $domain->delete();

        return redirect()
            ->route('dashboard')
            ->with('flash', ['type' => 'success', 'message' => 'Domain ve tum kayitli anahtar kelimeleri silindi.']);
    }

    public function dismissKeywordSuggestion(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanAccessDomain($request, $domain);

        $data = $request->validate(['phrase' => ['required', 'string', 'max:255']]);

        $domain->dismissKeywordSuggestion($data['phrase']);

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => 'Oneri kaldirildi.']);
    }
}
