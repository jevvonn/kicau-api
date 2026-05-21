<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Story;
use App\Models\UserBadge;
use App\Models\UserQuestionAnswer;
use App\Models\UserStat;
use Carbon\CarbonImmutable;

class BadgeService
{
    public function awardEligible(int $userId): array
    {
        $stat = UserStat::firstOrCreate(['user_id' => $userId]);

        $alreadyEarned = UserBadge::where('user_id', $userId)->pluck('badge_id', 'badge_id')->all();

        $badges = Badge::all()->keyBy('key');

        $newlyEarned = [];

        foreach ($this->evaluators($userId, $stat) as $key => $isEarned) {
            if (!$isEarned) {
                continue;
            }
            $badge = $badges->get($key);
            if (!$badge || isset($alreadyEarned[$badge->id])) {
                continue;
            }

            UserBadge::create([
                'user_id' => $userId,
                'badge_id' => $badge->id,
                'earned_at' => CarbonImmutable::now(),
            ]);

            $newlyEarned[] = $badge->key;
        }

        return $newlyEarned;
    }

    private function evaluators(int $userId, UserStat $stat): array
    {
        return [
            'master_cerita' => $stat->stories_completed >= 25,
            'petualang_cerdas' => $stat->stories_completed >= 10,
            'pembelajar_setia' => $stat->longest_streak_days >= 30,
            'pahlawan_berbagi' => $this->completedStoriesWithMoral($userId, 'berbagi') >= 5,
            'penabung_cilik' => $this->completedStoriesWithMoral($userId, 'menabung') >= 3,
            'si_jujur_hebat' => $this->hasConsecutiveCorrect($userId, 10),
        ];
    }

    private function completedStoriesWithMoral(int $userId, string $needle): int
    {
        return Story::query()
            ->where('stories.user_id', $userId)
            ->whereRaw('LOWER(stories.nilai_moral) LIKE ?', ['%' . strtolower($needle) . '%'])
            ->whereExists(function ($q) use ($userId) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('user_story_progress')
                    ->whereColumn('user_story_progress.story_id', 'stories.id')
                    ->where('user_story_progress.user_id', $userId)
                    ->whereNotNull('user_story_progress.completed_at');
            })
            ->count();
    }

    private function hasConsecutiveCorrect(int $userId, int $threshold): bool
    {
        $recent = UserQuestionAnswer::where('user_id', $userId)
            ->orderByDesc('id')
            ->limit($threshold)
            ->pluck('is_correct');

        if ($recent->count() < $threshold) {
            return false;
        }

        return $recent->every(fn($v) => (bool) $v === true);
    }
}
