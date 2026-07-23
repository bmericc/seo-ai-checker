<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\RunDomainWhoisLookup;
use App\Models\Check;
use App\Models\Domain;
use App\Models\DomainLlmApiKey;
use App\Services\Analytics\CompetitorAnalyzer;
use App\Services\Analytics\ScoreHistoryBuilder;
use App\Services\DomainCheckRunner;
use App\Services\Drift\DomainCheckDrift;
use App\Services\Ga4\Ga4Property;
use App\Services\Ga4\Ga4PropertyLister;
use App\Services\Google\GoogleTokenService;
use App\Support\Domain as DomainHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DomainController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['domain' => ['required', 'string']]);

        $domain = DomainHelper::fromFreeText($request->string('domain')->toString());

        if ($domain === null) {
            return back()->with('flash', ['type' => 'error', 'message' => __('Geçerli bir domain girin (örnek: example.com).')]);
        }

        $existing = Domain::query()
            ->where('domain', $domain)
            ->where('user_id', $request->user()->id)
            ->first();
        if ($existing !== null) {
            return redirect()
                ->route('domains.show', $existing)
                ->with('flash', ['type' => 'error', 'message' => __(':domain zaten kayıtlı.', ['domain' => $domain])]);
        }

        $record = $request->user()->domains()->create(['domain' => $domain]);

        RunDomainWhoisLookup::dispatch($record->id);

        return redirect()
            ->route('domains.show', $record)
            ->with('flash', ['type' => 'success', 'message' => __(':domain eklendi.', ['domain' => $domain])]);
    }

    private const MAX_SITEMAP_URLS_DISPLAYED = 200;

    public function show(Request $request, Domain $domain, ScoreHistoryBuilder $scoreHistoryBuilder): View
    {
        $this->ensureCanAccessDomain($request, $domain);

        $domain->load([
            'keywords' => fn ($q) => $q->orderBy('keyword'),
            'keywords.latestCheck',
            'latestDomainCheck',
            'user',
        ]);

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
        $scoreHistory = $scoreHistoryBuilder->groupedByDay($keywordChecks);

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

        $recentChecks = $domain->domainChecks()->limit(2)->get();
        $driftChanges = $recentChecks->count() === 2
            ? (new DomainCheckDrift())->diff($recentChecks[0], $recentChecks[1])
            : [];

        $domainCheckHistory = $scoreHistoryBuilder->domainCheckHistory(
            $domain->domainChecks()
                ->reorder()
                ->select(['id', 'domain_id', 'created_at', 'crux', 'gsc', 'ga4'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
        );

        $competitorAnalysis = (new CompetitorAnalyzer())->frequentCompetitors($domain->domain, $domain->keywords);

        return view('domains.show', [
            'domain' => $domain,
            'sitemapUrls' => $sitemapUrls,
            'sitemapUrlCounts' => $sitemapUrlCounts,
            'driftChanges' => $driftChanges,
            'scoreHistory' => $scoreHistory,
            'domainCheckHistory' => $domainCheckHistory,
            'competitorAnalysis' => $competitorAnalysis,
        ]);
    }

    public function check(Request $request, Domain $domain, DomainCheckRunner $checkRunner): RedirectResponse
    {
        $this->ensureCanAccessDomain($request, $domain);

        $checkRunner->run($domain);

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('Site kontrolü tamamlandı.')]);
    }

    public function refreshWhois(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanAccessDomain($request, $domain);

        RunDomainWhoisLookup::dispatch($domain->id);

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('WHOIS bilgisi kuyruğa alındı, birazdan güncellenecek.')]);
    }

    public function destroy(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanAccessDomain($request, $domain);

        $domain->delete();

        return redirect()
            ->route('dashboard')
            ->with('flash', ['type' => 'success', 'message' => __('Domain ve tüm kayıtlı anahtar kelimeleri silindi.')]);
    }

    public function dismissKeywordSuggestion(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanAccessDomain($request, $domain);

        $data = $request->validate(['phrase' => ['required', 'string', 'max:255']]);

        $domain->dismissKeywordSuggestion($data['phrase']);

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('Öneri kaldırıldı.')]);
    }

    public function updateGa4Property(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanAccessDomain($request, $domain);

        $data = $request->validate(['ga4_property_id' => ['nullable', 'string', 'max:64']]);

        $domain->update(['ga4_property_id' => $data['ga4_property_id'] ?: null]);

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('GA4 Property ID güncellendi.')]);
    }

    public function fetchGa4Properties(
        Request $request,
        Domain $domain,
        GoogleTokenService $tokenService,
        Ga4PropertyLister $lister,
    ): RedirectResponse {
        $this->ensureCanAccessDomain($request, $domain);

        $accessToken = $tokenService->getValidAccessToken($domain->user);
        $result = $lister->list($accessToken);

        if (!$result->configured) {
            return redirect()
                ->route('domains.show', $domain)
                ->with('flash', ['type' => 'error', 'message' => __('Google hesabınız bağlı değil.')]);
        }

        if ($result->error !== null) {
            return redirect()
                ->route('domains.show', $domain)
                ->with('flash', ['type' => 'error', 'message' => __('GA4 property listesi alınamadı: :error', ['error' => $result->error])]);
        }

        if ($result->properties === []) {
            return redirect()
                ->route('domains.show', $domain)
                ->with('flash', ['type' => 'error', 'message' => __('Erişebildiğiniz bir GA4 property bulunamadı.')]);
        }

        return redirect()
            ->route('domains.show', $domain)
            ->with('ga4Properties', array_map(
                fn (Ga4Property $property) => [
                    'propertyId' => $property->propertyId,
                    'label' => $property->label,
                    'accountName' => $property->accountName,
                ],
                $result->properties,
            ));
    }

    public function updateLlmVisibility(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureIsAdmin($request);

        $data = $request->validate(['llm_visibility_enabled' => ['required', 'boolean']]);

        $domain->update(['llm_visibility_enabled' => $data['llm_visibility_enabled']]);

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('AI görünürlük kontrolü ayarı güncellendi.')]);
    }

    public function storeLlmApiKey(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureIsAdmin($request);

        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in(DomainLlmApiKey::PROVIDERS)],
            'label' => ['required', 'string', Rule::in(DomainLlmApiKey::LABELS)],
            'api_key' => ['required', 'string', 'max:255'],
        ]);

        $domain->llmApiKeys()->updateOrCreate(
            ['provider' => $data['provider']],
            ['label' => $data['label'], 'api_key' => $data['api_key']],
        );

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('API key kaydedildi.')]);
    }

    public function destroyLlmApiKey(Request $request, Domain $domain, DomainLlmApiKey $llmApiKey): RedirectResponse
    {
        $this->ensureIsAdmin($request);
        abort_unless($llmApiKey->domain_id === $domain->id, 404);

        $llmApiKey->delete();

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('API key silindi.')]);
    }
}
