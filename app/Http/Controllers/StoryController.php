<?php

namespace App\Http\Controllers;

use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $imageBaseUrl = config('services.ai.image_base_url');
        $imageKey = config('services.ai.image_key');

        return response()->stream(function () use ($story, $imageBaseUrl, $imageKey) {
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

            if (empty($imageBaseUrl) || empty($imageKey)) {
                $send('error', ['message' => 'Layanan gambar AI belum dikonfigurasi.']);
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

            $previousImageBinary = null;
            $previousImageFilename = null;

            $maxAttempts = 3;

            foreach ($items as $index => $item) {
                $step = $index + 1;
                $prompt = (string) $item->image_prompt;

                $binary = null;
                $lastError = null;

                for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                    try {
                        if ($previousImageBinary === null) {
                            $response = Http::timeout(0)
                                ->when(app()->isLocal(), fn($h) => $h->withoutVerifying())
                                ->withHeaders(['Authorization' => 'Bearer ' . $imageKey])
                                ->acceptJson()
                                ->asJson()
                                ->post($imageBaseUrl . '/images/generations?api-version=2025-04-01-preview', [
                                    'prompt' => $prompt,
                                    'size' => '1536x1024',
                                    'quality' => 'low',
                                    'output_compression' => 100,
                                    'output_format' => 'jpeg',
                                    'n' => 1,
                                ]);
                        } else {
                            $response = Http::timeout(0)
                                ->when(app()->isLocal(), fn($h) => $h->withoutVerifying())
                                ->withHeaders(['Authorization' => 'Bearer ' . $imageKey])
                                ->acceptJson()
                                ->attach('image', $previousImageBinary, $previousImageFilename, ['Content-Type' => 'image/jpeg'])
                                ->asMultipart()
                                ->post($imageBaseUrl . '/images/edits?api-version=2025-04-01-preview', [
                                    ['name' => 'prompt', 'contents' => $prompt],
                                    ['name' => 'size', 'contents' => '1536x1024'],
                                    ['name' => 'quality', 'contents' => 'low'],
                                    ['name' => 'output_compression', 'contents' => 100],
                                    ['name' => 'output_format', 'contents' => 'jpeg'],
                                    ['name' => 'n', 'contents' => '1'],
                                ]);
                        }
                    } catch (\Throwable $e) {
                        $lastError = $e->getMessage();
                        $send('image_retry', [
                            'item_id' => $item->id,
                            'step' => $step,
                            'total' => $total,
                            'attempt' => $attempt,
                            'max_attempts' => $maxAttempts,
                            'message' => "Ilustrasi {$step} percobaan {$attempt} gagal: {$lastError}",
                        ]);
                        continue;
                    }

                    if ($response->failed()) {
                        $lastError = $response->json() ?? $response->body();
                        $send('image_retry', [
                            'item_id' => $item->id,
                            'step' => $step,
                            'total' => $total,
                            'attempt' => $attempt,
                            'max_attempts' => $maxAttempts,
                            'message' => "Ilustrasi {$step} percobaan {$attempt} gagal dibuat.",
                            'error' => $lastError,
                        ]);
                        continue;
                    }

                    $b64 = $response->json('data.0.b64_json');

                    if (!is_string($b64) || $b64 === '') {
                        $lastError = 'Respon AI tidak valid.';
                        $send('image_retry', [
                            'item_id' => $item->id,
                            'step' => $step,
                            'total' => $total,
                            'attempt' => $attempt,
                            'max_attempts' => $maxAttempts,
                            'message' => "Ilustrasi {$step} percobaan {$attempt} tidak tersedia.",
                        ]);
                        continue;
                    }

                    $decoded = base64_decode($b64, true);

                    if ($decoded === false) {
                        $lastError = 'Gagal dekode base64.';
                        $send('image_retry', [
                            'item_id' => $item->id,
                            'step' => $step,
                            'total' => $total,
                            'attempt' => $attempt,
                            'max_attempts' => $maxAttempts,
                            'message' => "Ilustrasi {$step} percobaan {$attempt} tidak dapat didekode.",
                        ]);
                        continue;
                    }

                    $binary = $decoded;
                    break;
                }

                if ($binary === null) {
                    $send('image_failed', [
                        'item_id' => $item->id,
                        'step' => $step,
                        'total' => $total,
                        'message' => "Ilustrasi {$step} gagal setelah {$maxAttempts} percobaan.",
                        'error' => $lastError,
                    ]);
                    continue;
                }

                $filename = Str::uuid() . '.jpeg';
                $path = 'generated-images/' . $filename;
                Storage::disk('public')->put($path, $binary);
                $imageUrl = url(Storage::url($path));

                $item->update(['image_url' => $imageUrl]);

                $previousImageBinary = $binary;
                $previousImageFilename = $filename;

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
