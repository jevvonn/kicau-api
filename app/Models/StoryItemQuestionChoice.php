<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryItemQuestionChoice extends Model
{
    protected $fillable = [
        'story_item_question_id',
        'text',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(StoryItemQuestion::class, 'story_item_question_id');
    }
}
