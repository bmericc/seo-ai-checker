<?php

declare(strict_types=1);

namespace App\Services\Competitors;

use App\Jobs\FetchCompetitorKeywords;
use App\Models\Domain;
use App\Models\DomainCompetitor;
use App\Models\User;

/**
 * Rakip secimi icin web arayuzu (DomainCompetitorController) ve MCP
 * (set_competitor) ortak yolu: kaydi olusturur/gunceller ve tracked
 * rakipler icin anahtar kelime cekme isini kuyruga koyar.
 */
final class CompetitorManager
{
    /**
     * @param  string  $host  Normalize edilmis rakip host'u (App\Support\Domain::fromFreeText).
     */
    public function upsert(Domain $domain, string $host, string $status, string $source, ?User $user): DomainCompetitor
    {
        $competitor = $domain->competitors()->firstOrNew(['competitor_domain' => $host]);
        $wasTracked = $competitor->exists && $competitor->isTracked();

        $competitor->fill([
            'status' => $status,
            // Elle eklenmis bir rakip sonradan SERP'ten secilirse kaynagi degismesin.
            'source' => $competitor->exists ? $competitor->source : $source,
            'added_by_user_id' => $competitor->added_by_user_id ?? $user?->id,
        ])->save();

        if ($competitor->isTracked() && (!$wasTracked || $competitor->keywords_fetched_at === null)) {
            $this->requestKeywords($competitor);
        }

        return $competitor;
    }

    /**
     * @return bool kuyruga konduysa true; zaten cekiliyorsa false
     */
    public function requestKeywords(DomainCompetitor $competitor): bool
    {
        if (!$competitor->isTracked() || $competitor->isFetchingKeywords()) {
            return false;
        }

        $competitor->update(['keywords_requested_at' => now()]);
        FetchCompetitorKeywords::dispatch($competitor->id);

        return true;
    }
}
