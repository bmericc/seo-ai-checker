<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Domain extends Model
{
    protected $fillable = ['domain', 'user_id', 'dismissed_keyword_suggestions'];

    protected $casts = [
        'dismissed_keyword_suggestions' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function domainChecks(): HasMany
    {
        return $this->hasMany(DomainCheck::class)->latest('created_at');
    }

    public function sitemapUrls(): HasMany
    {
        return $this->hasMany(SitemapUrl::class);
    }

    public function latestDomainCheck(): HasOne
    {
        return $this->hasOne(DomainCheck::class)->latestOfMany();
    }

    public function rootUrl(): string
    {
        return sprintf('https://%s/', $this->domain);
    }

    public function dismissKeywordSuggestion(string $phrase): void
    {
        $phrase = mb_strtolower(trim($phrase));
        $dismissed = $this->dismissed_keyword_suggestions ?? [];

        if (!in_array($phrase, $dismissed, true)) {
            $dismissed[] = $phrase;
            $this->update(['dismissed_keyword_suggestions' => $dismissed]);
        }
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        return $this->user_id !== null && $this->user_id === $user->id;
    }
}
