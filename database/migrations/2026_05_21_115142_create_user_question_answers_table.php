<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_question_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_item_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_item_question_choice_id')->constrained('story_item_question_choices')->cascadeOnDelete();
            $table->boolean('is_correct');
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'story_item_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_question_answers');
    }
};
