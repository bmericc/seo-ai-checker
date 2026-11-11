<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Lighthouse\LighthouseResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Check extends Model
{
    protected $fillable = [
        'keyword_id',
        'source',
        'blocked',
        'block_reason',
        'target_position',
        'organic_results',
        'mobile_target_position',
        'mobile_organic_results',
        'mobile_blocked',
        'mobile_block_reason',
        'ai_overview_present',
        'ai_overview_cited_domains',
        'ai_overview_target_cited',
        'ai_overview_note',
        'onpage',
        'onpage_error',
        'eeat_score',
        'eeat_breakdown',
        'lighthouse_performance',
        'lighthouse_seo',
        'lighthouse_accessibility',
        'lighthouse_best_practices',
        'lighthouse_error',
        'lighthouse_raw',
    ];

    protected $casts = [
        'blocked' => 'boolean',
        'organic_results' => 'array',
        'mobile_organic_results' => 'array',
        'mobile_blocked' => 'boolean',
        'ai_overview_present' => 'boolean',
        'ai_overview_cited_domains' => 'array',
        'ai_overview_target_cited' => 'boolean',
        'onpage' => 'array',
        'eeat_breakdown' => 'array',
        'lighthouse_raw' => 'array',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    /** Bu kontrolde calisan AI gorunurluk sorgusu (varsa). */
    public function aiCheck(): HasOne
    {
        return $this->hasOne(PromptExploration::class);
    }

    public function isVisibleTo(?User $user): bool
    {
        return $this->keyword->isVisibleTo($user);
    }

    /**
     * @return array<string, array{label: string, displayValue: ?string, score: ?float}>
     */
    public function lighthouseMetrics(): array
    {
        return LighthouseResult::metricsFromRaw($this->lighthouse_raw);
    }
}
