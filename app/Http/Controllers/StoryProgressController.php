<?php

namespace App\Http\Controllers;

use App\Models\Story;
use App\Models\StoryItemQuestion;
use App\Models\StoryItemQuestionChoice;
use App\Models\UserQuestionAnswer;
use App\Models\UserStat;
use App\Models\UserStoryProgress;
use App\Services\BadgeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoryProgressController extends Controller
{
    private const XP_PER_CORRECT_ANSWER = 10;
    private const XP_PER_STORY_COMPLETION = 100;

    public function answer(Request $request, Story $story, StoryItemQuestion $question)
    {
        $this->authorizeStory($request, $story);

        abort_if(
            $question->item->story_id !== $story->id,
            404,
            'Pertanyaan tidak ditemukan pada cerita ini.'
        );

        $validated = $request->validate([
            'choice_id' => 'required|integer|exists:story_item_question_choices,id',
        ]);

        $choice = StoryItemQuestionChoice::findOrFail($validated['choice_id']);

        abort_if(
            $choice->story_item_question_id !== $question->id,
            422,
            'Pilihan tidak sesuai dengan pertanyaan.'
        );

        $user = $request->user();

        $existing = UserQuestionAnswer::where('user_id', $user->id)
            ->where('story_item_question_id', $question->id)
            ->first();

        if ($existing) {
            return response()->json([
                'already_answered' => true,
                'is_correct' => $existing->is_correct,
                'xp_awarded' => 0,
                'total_xp' => $this->totalXp($user->id),
            ]);
        }

        $isCorrect = (bool) $choice->is_correct;
        $xp = $isCorrect ? self::XP_PER_CORRECT_ANSWER : 0;

        DB::transaction(function () use ($user, $question, $choice, $isCorrect, $xp) {
            UserQuestionAnswer::create([
                'user_id' => $user->id,
                'story_item_question_id' => $question->id,
                'story_item_question_choice_id' => $choice->id,
                'is_correct' => $isCorrect,
                'xp_awarded' => $xp,
            ]);

            if ($xp > 0) {
                UserStat::firstOrCreate(['user_id' => $user->id])->increment('total_xp', $xp);
            }
        });

        $newlyEarnedBadges = (new BadgeService())->awardEligible($user->id);

        return response()->json([
            'already_answered' => false,
            'is_correct' => $isCorrect,
            'xp_awarded' => $xp,
            'total_xp' => $this->totalXp($user->id),
            'newly_earned_badges' => $newlyEarnedBadges,
        ]);
    }

    public function complete(Request $request, Story $story)
    {
        $this->authorizeStory($request, $story);

        $user = $request->user();

        $progress = UserStoryProgress::firstOrNew([
            'user_id' => $user->id,
            'story_id' => $story->id,
        ]);

        if ($progress->exists && $progress->completed_at !== null) {
            return response()->json([
                'already_completed' => true,
                'xp_awarded' => 0,
                'total_xp' => $this->totalXp($user->id),
                'completed_at' => $progress->completed_at,
            ]);
        }

        $xp = self::XP_PER_STORY_COMPLETION;

        DB::transaction(function () use ($progress, $user, $xp) {
            $progress->fill([
                'completed_at' => CarbonImmutable::now(),
                'xp_awarded' => $xp,
            ])->save();

            $stat = UserStat::firstOrCreate(['user_id' => $user->id]);
            $stat->increment('total_xp', $xp);
            $stat->increment('stories_completed');
            $stat->increment('stories_attempted');
        });

        $newlyEarnedBadges = (new BadgeService())->awardEligible($user->id);

        return response()->json([
            'already_completed' => false,
            'xp_awarded' => $xp,
            'total_xp' => $this->totalXp($user->id),
            'completed_at' => $progress->completed_at,
            'newly_earned_badges' => $newlyEarnedBadges,
        ], 201);
    }

    private function authorizeStory(Request $request, Story $story): void
    {
        abort_if(
            $story->user_id !== $request->user()->id,
            403,
            'Anda tidak memiliki akses ke cerita ini.'
        );
    }

    private function totalXp(int $userId): int
    {
        return (int) UserStat::where('user_id', $userId)->value('total_xp');
    }
}
