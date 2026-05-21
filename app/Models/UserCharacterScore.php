<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCharacterScore extends Model
{
    protected $fillable = [
        'user_id',
        'character_value_id',
        'score',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function characterValue(): BelongsTo
    {
        return $this->belongsTo(CharacterValue::class);
    }
}
