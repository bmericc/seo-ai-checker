<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\FetchKeywordMetrics;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Keyword extends Model
{
    protected $fillable = [
        'domain_id',
        'keyword',
        'url',
        'search_volume',
        'cpc',
        'competition',
        'keyword_difficulty',
        'search_intent',
        'monthly_searches',
        'metrics_fetched_at',
        'metrics_error',
    ];

    protected $casts = [
        'search_volume' => 'integer',
        'cpc' => 'float',
        'competition' => 'float',
        'keyword_difficulty' => 'integer',
        'monthly_searches' => 'array',
        'metrics_fetched_at' => 'datetime',
    ];

    /**
     * Yeni eklenen her kelime (arayuz, oneri chip'i ya da MCP) icin
     * DataForSEO metrikleri arka planda cekilir. DataForSEO tanimli degilse
     * hicbir sey kuyruga birakilmaz.
     */
    protected static function booted(): void
    {
        static::created(function (Keyword $keyword) {
            if (config('seo.dataforseo.login') && config('seo.dataforseo.password')) {
                FetchKeywordMetrics::dispatch([$keyword->id])->afterCommit();
            }
        });
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(Check::class)->latest('created_at');
    }

    public function latestCheck(): HasOne
    {
        return $this->hasOne(Check::class)->latestOfMany();
    }

    /**
     * AI gorunurluk sonucu iceren en son kontrol. Haftalik otomatik
     * kontrol LLM saglayicilarini varsayilan olarak cagirmadigi icin
     * (bkz. config seo.scheduled_checks.include_llm) en son kontrol ile
     * en son LLM sonucu farkli kontroller olabilir.
     */
    public function latestLlmCheck(): HasOne
    {
        return $this->hasOne(Check::class)->ofMany(['id' => 'max'], fn ($query) => $query->whereNotNull('llm_visibility'));
    }

    /** Bu kelime icin kaydedilen yapay zeka kontrolleri (check_ai_answer). */
    public function aiChecks(): HasMany
    {
        return $this->hasMany(PromptExploration::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function targetUrl(): string
    {
        return $this->url ?: $this->domain->rootUrl();
    }

    public function isVisibleTo(?User $user): bool
    {
        return $this->domain->isVisibleTo($user);
    }
}
