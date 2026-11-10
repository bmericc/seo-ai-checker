<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Llm\LlmMentions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prompt Explorer: tek bir prompt'un, domain'e API key'i girilmis tum
 * yapay zeka saglayicilarina ayni anda sorulmasi. Sonuclar saglayici
 * bazinda "results" kolonunda tutulur (bkz. RunPromptExploration).
 */
class PromptExploration extends Model
{
    protected $fillable = [
        'domain_id',
        'user_id',
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
