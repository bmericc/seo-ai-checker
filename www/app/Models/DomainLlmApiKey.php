<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir domain icin ChatGPT/Claude/Gemini gorunurluk kontrolunde kullanilacak
 * saglayici basina bir API key. "label" (bireysel/musteri) sadece
 * yonetim/muhasebe amacli bir etikettir, davranisi degistirmez - hangi
 * "kese"den harcandigini admin'in takip edebilmesi icindir.
 */
class DomainLlmApiKey extends Model
{
    public const PROVIDERS = ['openai', 'anthropic', 'gemini'];

    public const LABELS = ['bireysel', 'musteri'];

    protected $fillable = [
        'domain_id',
        'provider',
        'label',
        'api_key',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function maskedKey(): string
    {
        $key = $this->api_key;

        return strlen($key) <= 8
            ? str_repeat('•', strlen($key))
            : substr($key, 0, 4) . str_repeat('•', 8) . substr($key, -4);
    }
}
