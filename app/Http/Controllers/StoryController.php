<?php

namespace App\Http\Controllers;

use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
                    'image_url' => $item['image_url'] ?? null,
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
