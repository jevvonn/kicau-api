<?php

namespace App\Http\Controllers;

use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;

class StoryController extends Controller
{
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'prompt' => 'required|string',
            'nilai_moral' => 'required|string',
            'story_idea' => 'required|string',
        ]);

        set_time_limit(0);

        $user = $request->user();

        $gatewayUrl = config('services.ai.gateway_url');

        if (empty($gatewayUrl)) {
            return response()->json([
                'message' => 'AI gateway belum dikonfigurasi.',
            ], 500);
        }

        $response = Http::timeout(0)
            ->when(app()->isLocal(), fn($h) => $h->withoutVerifying())
            ->acceptJson()
            ->asJson()
            ->post(rtrim($gatewayUrl, '/') . '/api/v1/story', $validated);

        if ($response->failed()) {
            return response()->json([
                'message' => 'Gagal menghubungi layanan AI.',
                'error' => $response->json() ?? $response->body(),
            ], 502);
        }

        $payload = $response->json();

        if (!is_array($payload) || empty($payload['title']) || !is_array($payload['story'] ?? null)) {
            return response()->json([
                'message' => 'Respon AI tidak valid.',
            ], 502);
        }

        $story = DB::transaction(function () use ($payload, $user) {
            $story = Story::create([
                'user_id' => $user->id,
                'title' => $payload['title'],
                'moral_message' => $payload['moral_message'] ?? null,
            ]);

            foreach ($payload['story'] as $index => $item) {
                $storyItem = $story->items()->create([
                    'order_index' => $item['order_index'] ?? $index,
                    'narrative' => $item['narrative'] ?? '',
                    'image_prompt' => $item['image_prompt'] ?? null,
                    'image_url' => null,
                ]);

                $question = $item['question'] ?? null;
                if (is_array($question) && !empty($question['prompt'])) {
                    $questionModel = $storyItem->question()->create([
                        'prompt' => $question['prompt'],
                    ]);

                    foreach (($question['choices'] ?? []) as $choice) {
                        $questionModel->choices()->create([
                            'text' => $choice['text'] ?? '',
                            'is_correct' => (bool) ($choice['is_correct'] ?? false),
                        ]);
                    }
                }
            }

            foreach (($payload['key_points'] ?? []) as $point) {
                if (!is_string($point) || $point === '') {
                    continue;
                }
                $story->keyPoints()->create(['point' => $point]);
            }

            return $story;
        });

        return response()->json($this->transform($story->fresh($this->relations())), 201);
    }

    public function show(Request $request, Story $story)
    {
        abort_if($story->user_id !== $request->user()->id, 403, 'Anda tidak memiliki akses ke cerita ini.');

        $story->load($this->relations());

        return response()->json($this->transform($story));
    }

    public function generateImages(Request $request, Story $story)
    {
        $tokenParam = $request->query('token');

        abort_if(!is_string($tokenParam) || $tokenParam === '', 401, 'Token tidak ditemukan.');

        $accessToken = PersonalAccessToken::findToken($tokenParam);

        abort_if(!$accessToken, 401, 'Token tidak valid.');

        $user = $accessToken->tokenable;

        abort_if(!$user, 401, 'Token tidak valid.');

        abort_if($story->user_id !== $user->id, 403, 'Anda tidak memiliki akses ke cerita ini.');

        set_time_limit(0);

        $gatewayUrl = config('services.ai.gateway_url');

        return response()->stream(function () use ($story, $gatewayUrl) {
            $send = function (string $event, array $data) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            $ping = function () {
                echo ": ping\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            if (empty($gatewayUrl)) {
                $send('error', ['message' => 'AI gateway belum dikonfigurasi.']);
                return;
            }

            $items = $story->items()
                ->whereNull('image_url')
                ->whereNotNull('image_prompt')
                ->orderBy('order_index')
                ->get();

            $total = $items->count();

            if ($total === 0) {
                $send('done', ['message' => 'Tidak ada ilustrasi yang perlu dibuat.']);
                return;
            }

            $send('start', ['total' => $total]);
            $send('progress', [
                'message' => "Membuat {$total} ilustrasi...",
                'total' => $total,
            ]);
            $ping();

            $prompts = $items->pluck('image_prompt')->values()->all();

            try {
                $data = [
                    'prompts' => $prompts,
                    'bucket' => 'generated-images',
                    'image_size' => '1280x720',
                ];

                $response = Http::timeout(0)
                    ->when(app()->isLocal(), fn($h) => $h->withoutVerifying())
                    ->acceptJson()
                    ->asJson()
                    ->post(rtrim($gatewayUrl, '/') . '/api/v1/image', $data);
            } catch (\Throwable $e) {
                $send('error', ['message' => $e->getMessage()]);
                return;
            }

            if ($response->failed()) {
                $send('error', [
                    'message' => 'Gagal menghubungi layanan AI.',
                    'error' => $response->json() ?? $response->body(),
                ]);
                return;
            }

            $imageUrls = $response->json('image_urls');

            if (!is_array($imageUrls)) {
                $send('error', ['message' => 'Respon AI tidak valid.']);
                return;
            }

            foreach ($items as $index => $item) {
                $step = $index + 1;
                $imageUrl = $imageUrls[$index] ?? null;

                if (!is_string($imageUrl) || $imageUrl === '') {
                    $send('image_failed', [
                        'item_id' => $item->id,
                        'step' => $step,
                        'total' => $total,
                        'message' => "Ilustrasi {$step} tidak tersedia.",
                    ]);
                    continue;
                }

                $item->update(['image_url' => $imageUrl]);

                $send('image_ready', [
                    'item_id' => $item->id,
                    'order_index' => $item->order_index,
                    'image_url' => $imageUrl,
                    'step' => $step,
                    'total' => $total,
                ]);
            }

            $send('done', ['message' => 'Semua ilustrasi selesai dibuat.', 'total' => $total]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    private function relations(): array
    {
        return [
            'items' => fn($q) => $q->orderBy('order_index'),
            'items.question.choices',
            'keyPoints',
        ];
    }

    private function transform(Story $story): array
    {
        return [
            'id' => $story->id,
            'user_id' => $story->user_id,
            'title' => $story->title,
            'story' => $story->items->map(function ($item) {
                $question = null;
                if ($item->question) {
                    $question = [
                        'prompt' => $item->question->prompt,
                        'choices' => $item->question->choices->map(fn($c) => [
                            'text' => $c->text,
                            'is_correct' => $c->is_correct,
                        ])->all(),
                    ];
                }

                return [
                    'order_index' => $item->order_index,
                    'narrative' => $item->narrative,
                    'image_prompt' => $item->image_prompt,
                    'image_url' => $item->image_url,
                    'question' => $question,
                ];
            })->all(),
            'moral_message' => $story->moral_message,
            'key_points' => $story->keyPoints->pluck('point')->all(),
            'created_at' => $story->created_at,
            'updated_at' => $story->updated_at,
        ];
    }
}
