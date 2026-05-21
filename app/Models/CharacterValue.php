<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CharacterValue extends Model
{
    protected $fillable = [
        'key',
        'label',
        'color',
        'sort_order',
    ];

    public function scores(): HasMany
    {
        return $this->hasMany(UserCharacterScore::class);
    }
}
