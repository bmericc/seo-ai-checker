<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\Domain;
use App\Models\ReportShare;
use App\Models\User;

/**
 * Paylasilabilir rapor linki olusturma - arayuz ve MCP ortak kullanir.
 */
final class ReportShareService
{
    /**
     * @param  list<string>|null  $sections  null: tum bolumler
     */
    public function create(Domain $domain, User $user, ?array $sections = null, ?int $expiresInDays = 30, ?string $title = null): ReportShare
    {
        $sections = $sections === null
            ? ReportShare::SECTIONS
            : array_values(array_intersect(ReportShare::SECTIONS, $sections));

        if ($sections === []) {
            $sections = ['summary'];
        }

        return $domain->reportShares()->create([
            'user_id' => $user->id,
            'token' => ReportShare::newToken(),
            'title' => $title !== null && trim($title) !== '' ? trim($title) : null,
            'sections' => $sections,
            'expires_at' => $expiresInDays !== null ? now()->addDays($expiresInDays) : null,
        ]);
    }
}
