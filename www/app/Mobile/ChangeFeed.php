<?php

declare(strict_types=1);

namespace App\Mobile;

use App\Models\Domain;
use App\Services\Analytics\VisibilityChanges;
use Illuminate\Support\Collection;

/**
 * Mobil "Değişiklikler" ekrani: kullanicinin gorebildigi tum domainlerde
 * son N gunde neyin kotulesip iyilestigi, etkisine gore tek listede.
 * Karsilastirma VisibilityChanges'ten gelir (MCP get_visibility_changes
 * ile ayni); burada yalnizca kullaniciya gosterilecek cumleye cevrilir.
 */
final class ChangeFeed
{
    /** Uygulamadaki secenekler: Bugun, 7 gun, 30 gun. */
    public const ALLOWED_DAYS = [1, 7, 30];

    public const LIMIT = 100;

    public function __construct(private readonly VisibilityChanges $changes)
    {
    }

    /**
     * @param  Collection<int, Domain>  $domains
     * @return array{days: int, items: list<array<string, mixed>>}
     */
    public function for(Collection $domains, int $days): array
    {
        $days = in_array($days, self::ALLOWED_DAYS, true) ? $days : VisibilityChanges::DEFAULT_DAYS;
        $items = [];

        foreach ($domains as $domain) {
            $result = $this->changes->forDomain($domain, $days);

            foreach ([...$result['losses'], ...$result['gains']] as $change) {
                $item = self::item($domain, $change);
                if ($item !== null) {
                    $items[] = $item;
                }
            }

            // Site kontrolu farklari (robots.txt, sitemap, llms.txt...) zaten
            // kullaniciya yonelik cumleler; yonu belli olmadigi icin notr.
            foreach ($result['site_changes'] as $text) {
                $items[] = [
                    'direction' => 'neutral',
                    'impact' => 30,
                    'title' => $text,
                    'domain_id' => $domain->id,
                    'domain' => $domain->domain,
                    'source' => __('Site kontrolü'),
                    'at' => null,
                ];
            }
        }

        usort($items, fn (array $a, array $b) => [$b['impact'], $b['at'] ?? ''] <=> [$a['impact'], $a['at'] ?? '']);

        return ['days' => $days, 'items' => array_slice($items, 0, self::LIMIT)];
    }

    /**
     * @param  array<string, mixed>  $change
     * @return ?array<string, mixed>
     */
    private static function item(Domain $domain, array $change): ?array
    {
        $keyword = $change['keyword'] ?? '';

        [$title, $source] = match ($change['type']) {
            'ai_overview_citation_lost' => [__('“:keyword” için AI Overview artık sizi kaynak göstermiyor', ['keyword' => $keyword]), 'AI Overview'],
            'ai_overview_citation_gained' => [__('AI Overview “:keyword” için sizi kaynak gösterdi', ['keyword' => $keyword]), 'AI Overview'],
            'ai_overview_appeared_without_citation' => [__('“:keyword” için sizi kaynak göstermeyen bir AI Overview çıktı', ['keyword' => $keyword]), 'AI Overview'],
            'ai_overview_disappeared' => [__('“:keyword” için AI Overview kalktı', ['keyword' => $keyword]), 'AI Overview'],
            'position_dropped' => [__('“:keyword” :from. sıradan :to. sıraya düştü', ['keyword' => $keyword, 'from' => $change['position_before'], 'to' => $change['position_after']]), __('Google sırası')],
            'position_improved' => [__('“:keyword” :from. sıradan :to. sıraya yükseldi', ['keyword' => $keyword, 'from' => $change['position_before'], 'to' => $change['position_after']]), __('Google sırası')],
            'ranking_lost' => [__('“:keyword” ilk 20 sonucun dışına çıktı (önceden :from.)', ['keyword' => $keyword, 'from' => $change['position_before']]), __('Google sırası')],
            'ranking_gained' => [__('“:keyword” :to. sıraya girdi', ['keyword' => $keyword, 'to' => $change['position_after']]), __('Google sırası')],
            'assistant_mentions_dropped' => [__(':assistant sizi daha az anıyor (%:before → %:after)', ['assistant' => $change['assistant'], 'before' => $change['mention_rate_before_pct'], 'after' => $change['mention_rate_after_pct']]), __('Yapay zekâ asistanları')],
            'assistant_mentions_rose' => [__(':assistant sizi daha sık anıyor (%:before → %:after)', ['assistant' => $change['assistant'], 'before' => $change['mention_rate_before_pct'], 'after' => $change['mention_rate_after_pct']]), __('Yapay zekâ asistanları')],
            'search_console_clicks_dropped' => [__('Search Console tıklamaları %:pct azaldı', ['pct' => abs((int) $change['change_pct'])]), 'Search Console'],
            'search_console_clicks_rose' => [__('Search Console tıklamaları %:pct arttı', ['pct' => abs((int) $change['change_pct'])]), 'Search Console'],
            default => [null, null],
        };

        if ($title === null) {
            return null;
        }

        return [
            'direction' => $change['direction'],
            'impact' => (int) $change['impact'],
            'title' => $title,
            'domain_id' => $domain->id,
            'domain' => $domain->domain,
            'source' => $source,
            'at' => $change['after_at'] ?? null,
        ];
    }
}
