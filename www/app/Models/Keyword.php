<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Keyword extends Model
{
    protected $fillable = ['domain_id', 'keyword', 'url'];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(Check::class)->latest('created_at');
    }

    public function latestCheck(): HasOne
    {
        return $this->hasOne(Check::class)->latestOfMany();
    }

    /** Bu kelime icin kaydedilen yapay zeka kontrolleri (check_ai_answer). */
    public function aiChecks(): HasMany
    {
        return $this->hasMany(PromptExploration::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function targetUrl(): string
    {
        return $this->url ?: $this->domain->rootUrl();
    }

    public function isVisibleTo(?User $user): bool
    {
        return $this->domain->isVisibleTo($user);
    }
}
