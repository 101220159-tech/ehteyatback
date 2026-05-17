<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Services\AiSentimentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderReviewController extends Controller
{
    protected function provider(Request $request)
    {
        $p = $request->user()->provider;
        abort_if(! $p, 404, 'Provider profile not found.');

        return $p;
    }

    public function index(Request $request): JsonResponse
    {
        $provider = $this->provider($request);
        $reviews = Review::query()
            ->where('provider_id', $provider->id)
            ->with(['customer', 'booking.service'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return ReviewResource::collection($reviews)->response();
    }

    /**
     * Sentiment analysis on this provider's reviews from the database (not CSV).
     */
    public function analyze(Request $request, AiSentimentService $ai): JsonResponse
    {
        try {
            return $this->analyzeReviews($request, $ai);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    protected function analyzeReviews(Request $request, AiSentimentService $ai): JsonResponse
    {
        $provider = $this->provider($request);

        $reviews = Review::query()
            ->where('provider_id', $provider->id)
            ->with(['customer'])
            ->orderByDesc('created_at')
            ->get();

        if ($reviews->isEmpty()) {
            return response()->json([
                'total_tested' => 0,
                'source' => 'database',
                'dataset' => 'Your reviews',
                'summary' => [
                    'predicted_positive' => 0,
                    'predicted_negative' => 0,
                ],
                'positive_reviews' => [],
                'negative_reviews' => [],
                'all_results' => [],
                'wrong_predictions' => [],
            ]);
        }

        $rows = $reviews->map(function (Review $review) {
            $text = trim((string) $review->comment);
            if ($text === '') {
                $text = "Customer gave {$review->rating} out of 5 stars.";
            }

            return [
                'id' => $review->id,
                'text' => $text,
                'rating' => (int) $review->rating,
                'customer_name' => $review->customer?->name,
            ];
        })->values()->all();

        $positive = [];
        $negative = [];
        $allResults = [];
        $wrong = [];

        foreach (array_chunk($rows, 50) as $chunk) {
            $texts = array_column($chunk, 'text');
            $batch = $ai->predictBatch($texts);

            foreach ($batch['all_results'] ?? [] as $item) {
                $idx = (int) ($item['index'] ?? 1) - 1;
                $meta = $chunk[$idx] ?? null;
                if (! $meta) {
                    continue;
                }

                $predicted = $item['sentiment'] ?? 'Positive';
                $confidence = $item['confidence'] ?? 0;
                $expected = self::expectedSentimentFromRating($meta['rating']);
                $match = $expected === null ? null : $expected === $predicted;

                $row = [
                    'id' => $meta['id'],
                    'review' => $meta['text'],
                    'predicted' => $predicted,
                    'confidence' => $confidence,
                    'rating' => $meta['rating'],
                    'customer_name' => $meta['customer_name'],
                    'expected' => $expected,
                    'match' => $match,
                    'keywords' => $item['keywords'] ?? [],
                ];

                $allResults[] = $row;

                if ($predicted === 'Positive') {
                    $positive[] = $row;
                } else {
                    $negative[] = $row;
                }

                if ($match === false && $expected !== null) {
                    $wrong[] = $row;
                }
            }
        }

        $total = count($allResults);
        $labeled = count(array_filter($allResults, fn ($r) => $r['expected'] !== null));
        $correct = count(array_filter($allResults, fn ($r) => $r['match'] === true));

        return response()->json([
            'total_tested' => $total,
            'source' => 'database',
            'dataset' => 'Your reviews',
            'summary' => [
                'predicted_positive' => count($positive),
                'predicted_negative' => count($negative),
            ],
            'positive_reviews' => $positive,
            'negative_reviews' => $negative,
            'all_results' => $allResults,
            'wrong_predictions' => $wrong,
            'labeled_count' => $labeled,
            'correct' => $correct,
            'accuracy_percent' => $labeled > 0 ? round(($correct / $labeled) * 100, 1) : null,
        ]);
    }

    private static function expectedSentimentFromRating(int $rating): ?string
    {
        if ($rating >= 4) {
            return 'Positive';
        }
        if ($rating <= 2) {
            return 'Negative';
        }

        return null;
    }
}
