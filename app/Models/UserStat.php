<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStat extends Model
{
    protected $fillable = [
        'user_id',
        'total_xp',
        'stories_completed',
        'stories_attempted',
        'current_streak_days',
        'longest_streak_days',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
