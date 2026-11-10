<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Kontroller kuyrukta (varsayilan dilde) calisir ve hata/uyari metinlerini
 * veritabanina Turkce olarak yazar. Bu sinif o kayitli metinleri gosterim
 * aninda bilinen kaliplarla eslestirip aktif dile cevirir. Eslesmeyen metin
 * (ornegin bir API'nin kendi hata mesaji) oldugu gibi doner.
 */
final class StoredText
{
    /**
     * Regex => [ceviri anahtari, yakalanan gruplarin placeholder adlari].
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private const PATTERNS = [
        '/^(\w+) boş yanıt döndü\.$/u' => [':provider boş yanıt döndü.', ['provider']],
        '/^(\w+) token sınırı aşıldı \((.+)\)\.$/u' => [':provider token sınırı aşıldı (:detail).', ['provider', 'detail']],
        '/^OpenAI isteği reddetti: (.+)$/us' => ['OpenAI isteği reddetti: :reason', ['reason']],
        '/^OpenAI tamamlanamadı \(finish_reason: (.+)\)\.$/u' => ['OpenAI tamamlanamadı (finish_reason: :reason).', ['reason']],
        '/^DataForSEO isteği başarısız oldu \(HTTP (\d+)\)\.$/u' => ['DataForSEO isteği başarısız oldu (HTTP :status).', ['status']],
        '/^DataForSEO hata döndürdü: (.+)$/us' => ['DataForSEO hata döndürdü: :error', ['error']],
        '/^DataForSEO görevi başarısız oldu: (.+)$/us' => ['DataForSEO görevi başarısız oldu: :error', ['error']],
        '/^Google istegine ulasilamadi: (.+)$/us' => ['Google isteğine ulaşılamadı: :error', ['error']],
        '/^Google istegi engellemis veya JS dogrulamasi istemis olabilir \(HTTP (\d+)\)\./u' => ['Google isteği engellemiş veya JS doğrulaması istemiş olabilir (HTTP :status). İstek sıklığını azaltıp farklı bir IP/proxy kullanmayı deneyin.', ['status']],
        '/^Beklenmeyen kok eleman: <(.+)>\.$/u' => ['Beklenmeyen kök eleman: <:element>.', ['element']],
        '/^Geçersiz dil\/bölge kodu: (.+)$/u' => ['Geçersiz dil/bölge kodu: :code', ['code']],
        '/^Yinelenen hreflang kodu: (.+) \((\d+) kez\)$/u' => ['Yinelenen hreflang kodu: :code (:count kez)', ['code', 'count']],
    ];

    /**
     * Kayitli metin => ceviri anahtari (ASCII yazilmis eski metinler dahil).
     *
     * @var array<string, string>
     */
    private const EXACT = [
        'AI Overview metni tespit edildi fakat icindeki kaynak linkleri otomatik olarak cikarilamadi; sayfayi manuel kontrol edin.' => 'AI Overview tespit edildi fakat içindeki kaynak linkleri otomatik olarak çıkarılamadı; sayfayı manuel kontrol edin.',
        'Gecersiz kok URL.' => 'Geçersiz kök URL.',
        'Bos yanit.' => 'Boş yanıt.',
    ];

    public static function t(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        if (isset(self::EXACT[$text])) {
            return __(self::EXACT[$text]);
        }

        foreach (self::PATTERNS as $regex => [$key, $names]) {
            if (preg_match($regex, $text, $m) === 1) {
                return __($key, array_combine($names, array_slice($m, 1, count($names))));
            }
        }

        return __($text);
    }
}
