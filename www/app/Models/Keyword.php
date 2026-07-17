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

    public function targetUrl(): string
    {
        return $this->url ?: sprintf('https://%s/', $this->domain->domain);
    }

    public function isVisibleTo(?User $user): bool
    {
        return $this->domain->isVisibleTo($user);
    }
}
