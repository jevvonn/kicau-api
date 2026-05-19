<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoryItemQuestion extends Model
{
    protected $fillable = [
        'story_item_id',
        'prompt',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(StoryItem::class, 'story_item_id');
    }

    public function choices(): HasMany
    {
        return $this->hasMany(StoryItemQuestionChoice::class);
    }
}
