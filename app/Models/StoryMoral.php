<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryMoral extends Model
{
    protected $fillable = [
        'story_id',
        'point',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
