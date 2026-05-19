<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StoryItem extends Model
{
    protected $fillable = [
        'story_id',
        'order_index',
        'narrative',
        'image_prompt',
        'image_url',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function question(): HasOne
    {
        return $this->hasOne(StoryItemQuestion::class);
    }
}
