<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Story extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'moral_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoryItem::class)->orderBy('order_index');
    }

    public function keyPoints(): HasMany
    {
        return $this->hasMany(StoryMoral::class);
    }
}
