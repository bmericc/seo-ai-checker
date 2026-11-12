<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\FetchKeywordMetrics;
use App\Jobs\FetchLlmMentions;
use App\Jobs\RunDomainSiteCheck;
use App\Jobs\RunDomainWhoisLookup;
use App\Models\Check;
use App\Models\Domain;
use App\Models\DomainLlmApiKey;
use App\Services\Analytics\AiOverviewVolatility;
use App\Services\Analytics\CompetitorAnalyzer;
use App\Services\Analytics\LlmShareOfModel;
use App\Services\Analytics\ScoreHistoryBuilder;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\Drift\DomainCheckDrift;
use App\Services\Ga4\Ga4Property;
use App\Services\Ga4\Ga4PropertyLister;
use App\Services\Google\GoogleTokenService;
use App\Support\Domain as DomainHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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

        // Ayni domain bu kullaniciyla zaten paylasildiysa ikinci bir kopya
        // olusturmak yerine paylasilan kayda yonlendir.
        $shared = Domain::query()
            ->accessibleBy($request->user())
            ->where('domain', $domain)
            ->first();
        if ($shared !== null) {
            return redirect()
                ->route('domains.show', $shared)
                ->with('flash', ['type' => 'success', 'message' => __(':domain sizinle zaten paylaşılmış.', ['domain' => $domain])]);
        }

        $record = $request->user()->domains()->create(['domain' => $domain]);

        RunDomainWhoisLookup::dispatch($record->id);

        return redirect()
            ->route('domains.show', $record)
            ->with('flash', ['type' => 'success', 'message' => __(':domain eklendi.', ['domain' => $domain])]);
    }

    private const MAX_SITEMAP_URLS_DISPLAYED = 200;

    public function show(Request $request, Domain $domain, ScoreHistoryBuilder $scoreHistoryBuilder, DataForSeoClient $dataForSeo): View
    {
        $this->ensureCanAccessDomain($request, $domain);

        $domain->load([
            'keywords' => fn ($q) => $q->orderBy('keyword'),
            'keywords.latestCheck',
            'keywords.latestAiCheck',
            // Satirda her asistanin son sonucunu birlestirmek icin
            // (bkz. Keyword::latestAiResultsByProvider).
            'keywords.aiChecks' => fn ($q) => $q->limit(10),
            'latestDomainCheck',
            'fact',
            'user',
            'members.user',
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

        $shareOfModel = LlmShareOfModel::forDomain($domain, $competitorAnalysis);

        $accessLevel = $domain->accessLevelFor($request->user());

        return view('domains.show', [
            'domain' => $domain,
            'accessLevel' => $accessLevel,
            'canEdit' => $domain->canBeEditedBy($request->user()),
            'canManage' => $domain->canBeManagedBy($request->user()),
            'membership' => $accessLevel === Domain::ACCESS_OWNER ? null : $domain->membershipFor($request->user()),
            'sitemapUrls' => $sitemapUrls,
            'sitemapUrlCounts' => $sitemapUrlCounts,
            'driftChanges' => $driftChanges,
            'scoreHistory' => $scoreHistory,
            'domainCheckHistory' => $domainCheckHistory,
            'competitorAnalysis' => $competitorAnalysis,
            'shareOfModel' => $shareOfModel,
            'aioStability' => AiOverviewVolatility::forKeywords($domain->keywords),
            'dataForSeoConfigured' => $dataForSeo->isConfigured(),
        ]);
    }

    public function check(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanEditDomain($request, $domain);

        // Site kontrolu proxy/Cloudflare timeout'unu asabildigi icin kuyruga birakilir.
        RunDomainSiteCheck::dispatch($domain->id, forceFresh: true);

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('Site kontrolü kuyruğa alındı, birazdan sonuçlar güncellenecek.')]);
    }

    public function refreshWhois(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanEditDomain($request, $domain);

        RunDomainWhoisLookup::dispatch($domain->id);

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('WHOIS bilgisi kuyruğa alındı, birazdan güncellenecek.')]);
    }

    public function destroy(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanManageDomain($request, $domain);

        $domain->delete();

        return redirect()
            ->route('dashboard')
            ->with('flash', ['type' => 'success', 'message' => __('Domain ve tüm kayıtlı anahtar kelimeleri silindi.')]);
    }

    /**
     * Arka plan site taramasi surerken domain sayfasindaki oneri kartinin
     * tazelenmesi icin: kartin HTML'i + taramanin hala surup surmedigi.
     */
    public function keywordSuggestions(Request $request, Domain $domain): JsonResponse
    {
        $this->ensureCanEditDomain($request, $domain);

        $domain->load('latestDomainCheck');

        return response()->json([
            'running' => ($domain->site_keyword_scan['status'] ?? null) === 'running',
            'html' => view('domains.partials.keyword-suggestions', ['domain' => $domain])->render(),
        ]);
    }

    public function dismissKeywordSuggestion(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanEditDomain($request, $domain);

        $data = $request->validate(['phrase' => ['required', 'string', 'max:255']]);

        $domain->dismissKeywordSuggestion($data['phrase']);

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('Öneri kaldırıldı.')]);
    }

    public function updateGa4Property(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanManageDomain($request, $domain);

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
        $this->ensureCanManageDomain($request, $domain);

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

    public function updateLlmScraper(Request $request, Domain $domain, DataForSeoClient $dataForSeo): RedirectResponse
    {
        $this->ensureIsAdmin($request);

        $data = $request->validate(['llm_scraper_enabled' => ['required', 'boolean']]);

        if ($data['llm_scraper_enabled'] && !$dataForSeo->isConfigured()) {
            return redirect()
                ->route('domains.show', $domain)
                ->with('flash', ['type' => 'error', 'message' => __('DataForSEO yapılandırılmamış (DATAFORSEO_LOGIN/DATAFORSEO_PASSWORD).')]);
        }

        $domain->update(['llm_scraper_enabled' => $data['llm_scraper_enabled']]);

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('ChatGPT/Gemini web arayüzü kontrolü ayarı güncellendi.')]);
    }

    public function fetchLlmMentions(Request $request, Domain $domain, DataForSeoClient $dataForSeo): RedirectResponse
    {
        $this->ensureIsAdmin($request);

        if (!$dataForSeo->isConfigured()) {
            return redirect()
                ->route('domains.show', $domain)
                ->with('flash', ['type' => 'error', 'message' => __('DataForSEO yapılandırılmamış (DATAFORSEO_LOGIN/DATAFORSEO_PASSWORD).')]);
        }

        FetchLlmMentions::dispatch($domain->id);

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('AI mention verisi arka planda getiriliyor. Birkaç dakika içinde sayfayı yenileyin.')]);
    }

    public function refreshKeywordMetrics(Request $request, Domain $domain, DataForSeoClient $dataForSeo): RedirectResponse
    {
        $this->ensureCanEditDomain($request, $domain);

        if (!$dataForSeo->isConfigured()) {
            return redirect()
                ->route('domains.show', $domain)
                ->with('flash', ['type' => 'error', 'message' => __('DataForSEO yapılandırılmamış (DATAFORSEO_LOGIN/DATAFORSEO_PASSWORD).')]);
        }

        $ids = $domain->keywords()->pluck('id')->all();
        if ($ids !== []) {
            FetchKeywordMetrics::dispatch($ids);
        }

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => __('Eksik ya da 30 günden eski anahtar kelime metrikleri arka planda getiriliyor.')]);
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
