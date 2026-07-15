<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    protected $fillable = ['domain'];

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }
}
