<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaySession extends Model
{
    protected $fillable = [
        'user_id',
        'played_on',
        'minutes',
    ];

    protected $casts = [
        'played_on' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
