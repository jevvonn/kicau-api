<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserQuestionAnswer extends Model
{
    protected $fillable = [
        'user_id',
        'story_item_question_id',
        'story_item_question_choice_id',
        'is_correct',
        'xp_awarded',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(StoryItemQuestion::class, 'story_item_question_id');
    }

    public function choice(): BelongsTo
    {
        return $this->belongsTo(StoryItemQuestionChoice::class, 'story_item_question_choice_id');
    }
}
