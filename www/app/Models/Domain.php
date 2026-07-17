<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Domain extends Model
{
    protected $fillable = ['domain'];

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function domainChecks(): HasMany
    {
        return $this->hasMany(DomainCheck::class)->latest('created_at');
    }

    public function latestDomainCheck(): HasOne
    {
        return $this->hasOne(DomainCheck::class)->latestOfMany();
    }

    public function rootUrl(): string
    {
        return sprintf('https://%s/', $this->domain);
    }
}
