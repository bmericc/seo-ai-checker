<?php

declare(strict_types=1);

namespace App\Services\Llm;

/**
 * Uc saglayici checker'inin (OpenAI/Anthropic/Gemini) ortak prompt mantigi.
 * Anahtar kelimeyi ciplak haliyle gondermek sohbet botlarinda ise yaramiyor
 * (ornegin "yunanistan" gibi tek kelimelik bir ulke adi gonderildiginde
 * model soru sorup aciklama istiyor, ya da site onermeden genel ansiklopedik
 * bilgi veriyor) - bu yuzden kelime "hangi web sitelerine bakmaliyim?" gibi
 * bir sorguya sarmalanir ve sistem talimati aciklama istemeyi/soru sormayi
 * acikca yasaklar. Google'a ciplak kelimeyi yazmak (GoogleSerpScraper) dogal
 * bir arama davranisiyken, bir sohbet botuna ayni ciplak kelimeyi yazmak
 * dogal degildir - iki arayuz farkli girdi bekler.
 */
final class LlmVisibilityPrompt
{
    public const SYSTEM_PROMPT = 'Bir kullanıcı arama motoruna bir şey yazıyormuş gibi düşün. '
        . 'Kullanıcıya soru sorma, ek açıklama isteme - doğrudan cevap ver ve mutlaka konuyla '
        . 'ilgili, güvenilir web sitelerinin isimlerini/adreslerini say.';

    public static function userPrompt(string $keyword): string
    {
        return sprintf('%s konusunda hangi web sitelerine bakmalıyım?', $keyword);
    }
}
