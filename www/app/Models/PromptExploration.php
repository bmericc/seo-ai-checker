<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Llm\LlmMentions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir domain icin yapilan tum yapay zeka gorunurluk kontrolleri: Prompt
 * Explorer sorulari, manuel MCP kontrolleri (check_ai_answer) ve otomatik
 * anahtar kelime kontrolleri (check_id dolu, bkz. CheckRunner). Sonuclar
 * saglayici bazinda "results" kolonunda tutulur.
 */
class PromptExploration extends Model
{
    protected $fillable = [
        'domain_id',
        'user_id',
        'keyword_id',
        'check_id',
        'prompt',
        'providers',
        'results',
        'completed_at',
    ];

    protected $casts = [
        'providers' => 'array',
        'results' => 'array',
        'completed_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    /** Otomatik anahtar kelime kontrolunden geldiyse o check. */
    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }

    public function isAutomatic(): bool
    {
        return $this->check_id !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    public function resultFor(string $provider): ?array
    {
        return $this->results[$provider] ?? null;
    }

    /**
     * Kac saglayici domain'i andi / kac saglayici cevap verdi.
     *
     * @return array{mentioned: int, answered: int}
     */
    public function mentionSummary(): array
    {
        $answered = collect($this->results ?? [])->filter(fn ($r) => ($r['error'] ?? null) === null);

        return [
            'mentioned' => $answered->where('present', true)->count(),
            'answered' => $answered->count(),
        ];
    }

    /**
     * Tum cevaplarda anilan siteler (domain'in kendisi haric), kac
     * saglayicida gectigine gore sirali.
     *
     * @return array<string, int>
     */
    public function mentionedSites(int $limit = 15): array
    {
        $own = LlmMentions::normalizeHost($this->domain->domain);
        $sites = [];

        foreach ($this->results ?? [] as $result) {
            $hosts = array_keys(LlmMentions::domainsIn((string) ($result['response'] ?? ''), $result['citations'] ?? []));
            foreach ($hosts as $host) {
                if ($host === $own || str_ends_with($host, '.' . $own)) {
                    continue;
                }
                $sites[$host] = ($sites[$host] ?? 0) + 1;
            }
        }

        arsort($sites);

        return array_slice($sites, 0, $limit, true);
    }
}
