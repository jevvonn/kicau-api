<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('story_item_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_item_id')->constrained()->cascadeOnDelete();
            $table->text('prompt');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_item_questions');
    }
};
