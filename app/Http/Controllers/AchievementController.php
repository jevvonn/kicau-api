<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\CharacterValue;
use App\Models\PlaySession;
use App\Models\UserBadge;
use App\Models\UserCharacterScore;
use App\Models\UserStat;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AchievementController extends Controller
{
    private const XP_PER_LEVEL = 200;

    public function index(Request $request)
    {
        $user = $request->user();

        $stat = UserStat::firstOrCreate(['user_id' => $user->id]);

        $level = intdiv($stat->total_xp, self::XP_PER_LEVEL) + 1;

        $successRate = $stat->stories_attempted > 0
            ? (int) round($stat->stories_completed / $stat->stories_attempted * 100)
            : 0;

        return response()->json([
            'profile' => [
                'name' => $user->name,
                'total_xp' => $stat->total_xp,
                'level' => $level,
                'level_title' => $this->levelTitle($level),
                'stars' => $this->stars($level),
            ],
            'stats' => [
                'stories_completed' => $stat->stories_completed,
                'success_rate' => $successRate,
                'current_streak_days' => $stat->current_streak_days,
                'longest_streak_days' => $stat->longest_streak_days,
            ],
            'play_minutes' => [
                'today' => $this->minutesForDay($user->id, CarbonImmutable::today()),
                'series' => [
                    'hari' => $this->dailySeries($user->id),
                    'minggu' => $this->weeklySeries($user->id),
                    'bulan' => $this->monthlySeries($user->id),
                ],
            ],
            'character_scores' => $this->characterScores($user->id),
            'badges' => $this->badges($user->id),
        ]);
    }

    public function playMinutes(Request $request)
    {
        $validated = $request->validate([
            'period' => 'required|in:hari,minggu,bulan',
        ]);

        $user = $request->user();

        $series = match ($validated['period']) {
            'hari' => $this->dailySeries($user->id),
            'minggu' => $this->weeklySeries($user->id),
            'bulan' => $this->monthlySeries($user->id),
        };

        return response()->json([
            'period' => $validated['period'],
            'series' => $series,
        ]);
    }

    public function logPlay(Request $request)
    {
        $validated = $request->validate([
            'minutes' => 'required|integer|min:1|max:1440',
            'played_on' => 'nullable|date',
        ]);

        $user = $request->user();
        $playedOn = isset($validated['played_on'])
            ? CarbonImmutable::parse($validated['played_on'])->toDateString()
            : CarbonImmutable::today()->toDateString();

        $session = PlaySession::create([
            'user_id' => $user->id,
            'played_on' => $playedOn,
            'minutes' => $validated['minutes'],
        ]);

        return response()->json($session, 201);
    }

    private function minutesForDay(int $userId, CarbonImmutable $date): int
    {
        return (int) PlaySession::where('user_id', $userId)
            ->whereDate('played_on', $date->toDateString())
            ->sum('minutes');
    }

    private function dailySeries(int $userId): array
    {
        $today = CarbonImmutable::today();
        $start = $today->startOfWeek(CarbonImmutable::MONDAY);
        $labels = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

        $rows = PlaySession::where('user_id', $userId)
            ->whereBetween('played_on', [$start->toDateString(), $start->addDays(6)->toDateString()])
            ->select('played_on', DB::raw('SUM(minutes) as total'))
            ->groupBy('played_on')
            ->pluck('total', 'played_on');

        $series = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->addDays($i)->toDateString();
            $series[] = [
                'label' => $labels[$i],
                'value' => (int) ($rows[$day] ?? 0),
            ];
        }

        return $series;
    }

    private function weeklySeries(int $userId): array
    {
        $today = CarbonImmutable::today();
        $series = [];

        for ($i = 6; $i >= 0; $i--) {
            $weekStart = $today->startOfWeek(CarbonImmutable::MONDAY)->subWeeks($i);
            $weekEnd = $weekStart->addDays(6);

            $total = (int) PlaySession::where('user_id', $userId)
                ->whereBetween('played_on', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->sum('minutes');

            $series[] = [
                'label' => 'M' . (7 - $i),
                'value' => $total,
            ];
        }

        return $series;
    }

    private function monthlySeries(int $userId): array
    {
        $today = CarbonImmutable::today();
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $series = [];

        for ($i = 3; $i >= 0; $i--) {
            $monthStart = $today->startOfMonth()->subMonths($i);
            $monthEnd = $monthStart->endOfMonth();

            $total = (int) PlaySession::where('user_id', $userId)
                ->whereBetween('played_on', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->sum('minutes');

            $series[] = [
                'label' => $labels[$monthStart->month - 1],
                'value' => $total,
            ];
        }

        return $series;
    }

    private function characterScores(int $userId): array
    {
        $values = CharacterValue::orderBy('sort_order')->get();
        $scores = UserCharacterScore::where('user_id', $userId)
            ->pluck('score', 'character_value_id');

        return $values->map(fn(CharacterValue $v) => [
            'key' => $v->key,
            'label' => $v->label,
            'color' => $v->color,
            'value' => (int) ($scores[$v->id] ?? 0),
        ])->all();
    }

    private function badges(int $userId): array
    {
        $badges = Badge::orderBy('sort_order')->get();
        $earned = UserBadge::where('user_id', $userId)
            ->pluck('earned_at', 'badge_id');

        return $badges->map(fn(Badge $b) => [
            'id' => $b->id,
            'key' => $b->key,
            'name' => $b->name,
            'description' => $b->description,
            'icon' => $b->icon,
            'color' => $b->color,
            'is_achieved' => isset($earned[$b->id]),
            'earned_at' => $earned[$b->id] ?? null,
        ])->all();
    }

    private function levelTitle(int $level): string
    {
        return match (true) {
            $level >= 20 => 'Pembelajar Berlian',
            $level >= 15 => 'Pembelajar Emas',
            $level >= 10 => 'Pembelajar Perak',
            $level >= 5 => 'Pembelajar Perunggu',
            default => 'Pembelajar Pemula',
        };
    }

    private function stars(int $level): int
    {
        return max(1, min(5, intdiv($level, 2) + 1));
    }
}
