<?php

namespace App\Http\Controllers;

use App\Models\ChatbotMessage;
use App\Models\ChatbotSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
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

    $response = Http::withToken(config('services.ai.key'))
      ->timeout(120)
      ->when(app()->isLocal(), fn($h) => $h->withoutVerifying())
      ->acceptJson()
      ->asJson()
      ->post(rtrim(config('services.ai.base_url'), '/') . '/chat/completions', [
        'model' => config('services.ai.model'),
        'messages' => $history,
        'max_tokens' => 1024,
      ]);

    if ($response->failed()) {
      $userMessage->delete();

      return response()->json([
        'message' => 'Gagal menghubungi layanan AI.',
        'error' => $response->json() ?? $response->body(),
      ], 502);
    }

    $reply = $response->json('choices.0.message.content');

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
}
