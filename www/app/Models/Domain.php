<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Domain extends Model
{
    protected $fillable = [
        'domain',
        'user_id',
        'dismissed_keyword_suggestions',
        'ga4_property_id',
        'llm_visibility_enabled',
    ];

    protected $casts = [
        'dismissed_keyword_suggestions' => 'array',
        'llm_visibility_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function domainChecks(): HasMany
    {
        // "id" ikincil siralama olarak eklenir: created_at ayni saniyeye
        // denk gelen ardisik kontrollerde (ornegin testlerde, ya da hizli
        // art arda "Site Kontrolu Yap" tiklamalarinda) tek basina
        // created_at siralamasi belirsiz kalabilir.
        return $this->hasMany(DomainCheck::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function sitemapUrls(): HasMany
    {
        return $this->hasMany(SitemapUrl::class);
    }

    public function llmApiKeys(): HasMany
    {
        return $this->hasMany(DomainLlmApiKey::class);
    }

    public function latestDomainCheck(): HasOne
    {
        return $this->hasOne(DomainCheck::class)->latestOfMany();
    }

    /**
     * Ayni domain string'ini (ornegin farkli kullanicilar) takip eden TUM
     * Domain kayitlarinin paylastigi ortak gercekler - bkz. DomainFact.
     * Domain.id'ye degil domain STRING'ine bagli oldugu icin normal bir
     * foreign key degil, "domain" kolonuyla eslesen tek satirdir.
     */
    public function fact(): BelongsTo
    {
        return $this->belongsTo(DomainFact::class, 'domain', 'domain');
    }

    /**
     * Girilen domain'in kok URL'i. Bilinen bir kalici (301/308) yonlendirme
     * varsa (bkz. CanonicalHostChecker, DomainFact.canonical_host) girilen
     * domain yerine gercek trafigin gittigi host kullanilir - ornegin
     * "domain.com" yerine "www.domain.com". Site kontrolu hic
     * calistirilmadiysa (kanonik host henuz bilinmiyorsa) girilen domain'e
     * geri doner. Sistemde "URL girilmediginde" varsayilan hedef her zaman
     * budur (bkz. Keyword::targetUrl()).
     */
    public function rootUrl(): string
    {
        $canonicalHost = $this->fact?->canonical_host['canonical_host'] ?? null;
        $host = is_string($canonicalHost) && $canonicalHost !== '' ? $canonicalHost : $this->domain;

        return sprintf('https://%s/', $host);
    }

    /**
     * Her anahtar kelimenin EN SON kontrolune bakarak (kw.latestCheck eager
     * load edilmis olmali) "su an" domain'in AI Overview'da ne kadar
     * gorunur oldugunu ozetler: skor = kaynak gosterilen / AI Overview
     * gozlemlenen anahtar kelime orani. AI Overview hicbir anahtar
     * kelimede gozlemlenmediyse skor null'dur (henuz olculebilir bir sey
     * yok, 0% ile karistirilmamali).
     *
     * @return array{score: ?float, cited_count: int, present_count: int, checked_count: int}
     */
    public function aiVisibilitySnapshot(): array
    {
        $latestChecks = $this->keywords->map->latestCheck->filter();
        $present = $latestChecks->where('ai_overview_present', true);
        $cited = $present->where('ai_overview_target_cited', true);

        return [
            'score' => $present->isNotEmpty() ? round(($cited->count() / $present->count()) * 100, 1) : null,
            'cited_count' => $cited->count(),
            'present_count' => $present->count(),
            'checked_count' => $latestChecks->count(),
        ];
    }

    /**
     * WHOIS artik domain string'ine gore paylasilan DomainFact'te tutulur -
     * bkz. Domain::fact(). Henuz hic sorgulanmadiysa (fact yok ya da
     * whois_registered_at bilinmiyorsa) null doner.
     */
    public function whoisAgeInYears(): ?int
    {
        return $this->fact?->whoisAgeInYears();
    }

    public function dismissKeywordSuggestion(string $phrase): void
    {
        $phrase = mb_strtolower(trim($phrase));
        $dismissed = $this->dismissed_keyword_suggestions ?? [];

        if (!in_array($phrase, $dismissed, true)) {
            $dismissed[] = $phrase;
            $this->update(['dismissed_keyword_suggestions' => $dismissed]);
        }
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        return $this->user_id !== null && $this->user_id === $user->id;
    }
}
