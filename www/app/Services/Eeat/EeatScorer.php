<?php

declare(strict_types=1);

namespace App\Services\Eeat;

use App\Models\DomainFact;
use App\Services\OnPage\OnPageSeoResult;

/**
 * Google'in E-E-A-T (Experience, Expertise, Authoritativeness,
 * Trustworthiness) kalite kavramindan esinlenen, tamamen mevcut verilerden
 * (on-page tarama + domain-genelinde paylasilan gercekler) turetilen basit,
 * seffaf bir skor - yapay zeka/NLP kullanmaz, her sinyal acikca sebebiyle
 * birlikte gosterilebilir. Amac kesin bir "Google skoru" iddia etmek degil,
 * hem klasik SEO hem de AI cevap motorlarinin (bkz. LLM gorunurluk kontrolu)
 * bir sayfaya ne kadar "guvenilir kaynak" olarak bakabilecegine dair somut,
 * aksiyon alinabilir sinyaller vermektir.
 */
final class EeatScorer
{
    public function score(OnPageSeoResult $onPage, ?DomainFact $fact, ?array $bingBacklinks): EeatResult
    {
        $signals = [];

        $signals['author'] = $this->signal($onPage->authorDetected, 15);

        $signals['published_date'] = $this->signal($onPage->publishedDateDetected, 10);

        $ageYears = $fact?->whoisAgeInYears();
        $agePoints = match (true) {
            $ageYears === null => 0,
            $ageYears >= 5 => 15,
            $ageYears >= 2 => 10,
            $ageYears >= 1 => 5,
            default => 0,
        };
        $signals['domain_age'] = $this->signal($agePoints > 0, 15, $agePoints);

        $hasBacklinks = ($bingBacklinks['verified'] ?? false) && (($bingBacklinks['total_links'] ?? 0) > 0);
        $signals['backlinks'] = $this->signal($hasBacklinks, 10);

        $httpsEnforced = (bool) ($fact?->security_headers['http_redirects_to_https'] ?? false);
        $signals['https'] = $this->signal($httpsEnforced, 10);

        $securityHeaderCount = count(array_filter($fact?->security_headers['headers'] ?? []));
        $signals['security_headers'] = $this->signal($securityHeaderCount >= 2, 10);

        $hasAboutOrContact = $onPage->aboutPageLinked || $onPage->contactPageLinked;
        $signals['about_contact'] = $this->signal($hasAboutOrContact, 10);

        $hasDepth = $onPage->wordCount >= 300;
        $signals['content_depth'] = $this->signal($hasDepth, 10);

        $signals['structured_data'] = $this->signal($onPage->hasStructuredData, 10);

        $expertise = $signals['author']['points'] + $signals['published_date']['points'];
        $authority = $signals['domain_age']['points'] + $signals['backlinks']['points'];
        $trust = $signals['https']['points'] + $signals['security_headers']['points'] + $signals['about_contact']['points'];
        $experience = $signals['content_depth']['points'] + $signals['structured_data']['points'];

        return new EeatResult(
            score: $expertise + $authority + $trust + $experience,
            expertiseScore: $expertise,
            authorityScore: $authority,
            trustScore: $trust,
            experienceScore: $experience,
            signals: $signals,
        );
    }

    /**
     * @return array{present: bool, points: int, max: int}
     */
    private function signal(bool $present, int $max, ?int $points = null): array
    {
        return [
            'present' => $present,
            'points' => $points ?? ($present ? $max : 0),
            'max' => $max,
        ];
    }
}
