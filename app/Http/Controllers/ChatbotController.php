<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\CharacterValue;
use App\Models\ChatbotMessage;
use App\Models\ChatbotSession;
use App\Models\PlaySession;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserCharacterScore;
use App\Models\UserStat;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
  private const XP_PER_LEVEL = 200;

  public function index(Request $request)
  {
    $sessions = $request->user()
      ->chatbotSessions()
      ->latest()
      ->get();

    return response()->json($sessions);
  }

  public function show(Request $request, ChatbotSession $session)
  {
    $this->authorizeSession($request, $session);

    $session->load(['messages' => fn($q) => $q->orderBy('id')]);

    return response()->json($session);
  }

  public function destroy(Request $request, ChatbotSession $session)
  {
    $this->authorizeSession($request, $session);

    $session->delete();

    return response()->json(['message' => 'Session deleted successfully.']);
  }

  public function chat(Request $request)
  {
    $request->validate([
      'session_id' => 'nullable|integer|exists:chatbot_sessions,id',
      'prompt' => 'required|string',
    ], [
      'prompt.required' => 'Prompt wajib diisi.',
      'prompt.string' => 'Prompt harus berupa teks.',
      'session_id.exists' => 'Sesi chatbot tidak ditemukan.',
    ]);

    $user = $request->user();

    if ($request->filled('session_id')) {
      $session = ChatbotSession::where('id', $request->session_id)
        ->where('user_id', $user->id)
        ->firstOrFail();
    } else {
      $session = ChatbotSession::create([
        'user_id' => $user->id,
        'title' => Str::limit($request->prompt, 50),
      ]);
    }

    $userMessage = $session->messages()->create([
      'role' => 'user',
      'content' => $request->prompt,
    ]);

    $history = $session->messages()
      ->orderBy('id')
      ->get(['role', 'content'])
      ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
      ->all();

    $messages = array_merge(
      [['role' => 'system', 'content' => $this->buildAchievementContext($user)]],
      $history
    );

    $response = Http::withToken(config('services.ai.key'))
      ->timeout(0)
      ->when(app()->isLocal(), fn($h) => $h->withoutVerifying())
      ->acceptJson()
      ->asJson()
      ->post(rtrim(config('services.ai.gateway_url'), '/') . '/api/v1/chat', [
        'messages' => $messages,
      ]);

    if ($response->failed()) {
      $userMessage->delete();

      return response()->json([
        'message' => 'Gagal menghubungi layanan AI.',
        'error' => $response->json() ?? $response->body(),
      ], 502);
    }

    $reply = $response->json('messages.0.content');

    if (!is_string($reply) || $reply === '') {
      $userMessage->delete();

      return response()->json([
        'message' => 'Respon AI tidak valid.',
      ], 502);
    }

    $assistantMessage = $session->messages()->create([
      'role' => 'assistant',
      'content' => $reply,
    ]);

    return response()->json([
      'session' => $session->fresh(),
      'user_message' => $userMessage,
      'reply' => $assistantMessage,
    ]);
  }

  private function authorizeSession(Request $request, ChatbotSession $session): void
  {
    abort_if($session->user_id !== $request->user()->id, 403, 'Anda tidak memiliki akses ke sesi ini.');
  }

  private function buildAchievementContext(User $user): string
  {
    $stat = UserStat::firstOrCreate(['user_id' => $user->id]);
    $level = intdiv($stat->total_xp, self::XP_PER_LEVEL) + 1;
    $successRate = $stat->stories_attempted > 0
      ? (int) round($stat->stories_completed / $stat->stories_attempted * 100)
      : 0;

    $today = CarbonImmutable::today();
    $weekStart = $today->startOfWeek(CarbonImmutable::MONDAY);
    $monthStart = $today->startOfMonth();

    $minutesToday = (int) PlaySession::where('user_id', $user->id)
      ->whereDate('played_on', $today->toDateString())
      ->sum('minutes');
    $minutesWeek = (int) PlaySession::where('user_id', $user->id)
      ->whereBetween('played_on', [$weekStart->toDateString(), $weekStart->addDays(6)->toDateString()])
      ->sum('minutes');
    $minutesMonth = (int) PlaySession::where('user_id', $user->id)
      ->whereBetween('played_on', [$monthStart->toDateString(), $monthStart->endOfMonth()->toDateString()])
      ->sum('minutes');

    $characterValues = CharacterValue::orderBy('sort_order')->get();
    $scores = UserCharacterScore::where('user_id', $user->id)->pluck('score', 'character_value_id');
    $characterLines = $characterValues
      ->map(fn(CharacterValue $v) => '- ' . $v->label . ': ' . (int) ($scores[$v->id] ?? 0))
      ->implode("\n");

    $badges = Badge::orderBy('sort_order')->get();
    $earned = UserBadge::where('user_id', $user->id)->pluck('earned_at', 'badge_id');
    $earnedBadges = $badges->filter(fn(Badge $b) => isset($earned[$b->id]))
      ->map(fn(Badge $b) => '- ' . $b->name . ' (' . $b->description . ')')
      ->implode("\n");
    $lockedBadges = $badges->filter(fn(Badge $b) => !isset($earned[$b->id]))
      ->map(fn(Badge $b) => '- ' . $b->name . ' (' . $b->description . ')')
      ->implode("\n");

    return <<<CTX
Kamu adalah asisten pembelajaran ramah anak untuk aplikasi Kicau. Yang bertanya sekarang adalah orang tua. Selalu gunakan data progres pengguna di bawah ini ketika menjawab pertanyaan tentang kemajuan, statistik, badge, level, atau aktivitas belajar mereka. Jawab dalam Bahasa Indonesia yang hangat dan memotivasi.

Profil Pengguna (Anak):
- Nama: {$user->name}
- Total XP: {$stat->total_xp}
- Level: {$level}
- XP per level: 200

Statistik Cerita (Anak):
- Cerita selesai: {$stat->stories_completed}
- Cerita dicoba: {$stat->stories_attempted}
- Tingkat keberhasilan: {$successRate}%
- Streak hari ini: {$stat->current_streak_days} hari
- Streak terpanjang: {$stat->longest_streak_days} hari

Waktu Bermain (Anak):
- Hari ini: {$minutesToday} menit
- Minggu ini: {$minutesWeek} menit
- Bulan ini: {$minutesMonth} menit

Skor Karakter (Anak):
{$characterLines}

Badge yang Sudah Diraih (Anak):
{$earnedBadges}

Badge yang Belum Diraih (Anak):
{$lockedBadges}
CTX;
  }
}
