<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\Review;
use App\Services\AiSentimentService;
use App\Services\LocalRetentionPredictor;
use App\Services\RetentionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
     * Reviews grouped by customer — for pie chart and per-customer timeline (no AI).
     */
    public function customersSummary(Request $request): JsonResponse
    {
        $provider = $this->provider($request);

        $reviews = Review::query()
            ->where('provider_id', $provider->id)
            ->with(['customer'])
            ->orderByDesc('created_at')
            ->get();

        $customers = $reviews
            ->groupBy('customer_id')
            ->map(function ($items, $customerId) {
                $customer = $items->first()->customer;

                return [
                    'customer_id'   => (string) $customerId,
                    'customer_name' => $customer?->name ?? 'Unknown customer',
                    'review_count'  => $items->count(),
                    'reviews'       => $items->map(fn (Review $r) => [
                        'id'         => $r->id,
                        'rating'     => (int) $r->rating,
                        'comment'    => $r->comment,
                        'created_at' => $r->created_at,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->sortByDesc('review_count')
            ->values()
            ->all();

        $chart = array_map(fn (array $c) => [
            'name'  => $c['customer_name'],
            'count' => $c['review_count'],
        ], $customers);

        return response()->json([
            'total_reviews'    => $reviews->count(),
            'unique_customers' => count($customers),
            'customers'        => $customers,
            'chart'            => $chart,
        ]);
    }

    /**
     * AI reorder probability per customer (bookings + reviews → retention-service).
     */
    public function retentionInsights(Request $request, RetentionService $retention): JsonResponse
    {
        return $this->buildRetentionInsights($request, $retention);
    }

    protected function buildRetentionInsights(Request $request, RetentionService $retention): JsonResponse
    {
        $provider = $this->provider($request);

        $bookings = Booking::query()
            ->where('provider_id', $provider->id)
            ->with(['customer', 'review'])
            ->orderByDesc('scheduled_at')
            ->get();

        $reviews = Review::query()
            ->where('provider_id', $provider->id)
            ->with('customer')
            ->get();

        $customerIds = $bookings->pluck('customer_id')
            ->merge($reviews->pluck('customer_id'))
            ->unique()
            ->filter();

        if ($customerIds->isEmpty()) {
            return response()->json([
                'customers' => [],
                'ai_available' => true,
                'insights' => [
                    'insights' => ['No customers with bookings or reviews yet.'],
                    'segments' => [],
                    'summary' => [
                        'total_customers' => 0,
                        'avg_repeat_probability' => 0,
                        'high_risk_count' => 0,
                        'vip_count' => 0,
                    ],
                ],
            ]);
        }

        $predictions = [];
        $aiAvailable = true;
        $localPredictor = new LocalRetentionPredictor;

        foreach ($customerIds as $customerId) {
            $customerBookings = $bookings->where('customer_id', $customerId);
            $customerReviews = $reviews->where('customer_id', $customerId);
            $customer = $customerBookings->first()?->customer
                ?? $customerReviews->first()?->customer;

            $payload = $this->retentionPayloadForCustomer(
                $customerBookings,
                $customerReviews,
                $customer?->name ?? 'Customer'
            );

            try {
                $result = $retention->predict($payload);
            } catch (\RuntimeException $e) {
                if ($aiAvailable) {
                    Log::warning('Retention service unavailable, using local estimates.', [
                        'message' => $e->getMessage(),
                    ]);
                    $aiAvailable = false;
                }
                $result = $localPredictor->predict($payload);
            }

            $predictions[] = [
                'customer_id' => (string) $customerId,
                'customer' => $customer?->name ?? $result['customer_name'] ?? 'Customer',
                'repeat_probability' => $result['repeat_order_probability'] ?? 0,
                'loyalty_score' => $result['loyalty_score'] ?? 0,
                'sentiment_score' => $result['sentiment_score'] ?? 0,
                'risk' => $result['churn_risk'] ?? 'Medium',
                'loyalty_level' => $result['loyalty_level'] ?? 'Medium',
                'last_order_date' => $result['last_order_date'] ?? null,
                'avg_rating' => $result['avg_rating'] ?? 0,
                'total_orders' => $result['total_orders'] ?? 0,
                'is_vip' => (bool) ($result['is_vip'] ?? false),
                'estimated_ltv' => $result['estimated_ltv'] ?? 0,
                'segment' => $result['segment'] ?? null,
                'review_count' => $customerReviews->count(),
            ];
        }

        usort($predictions, fn ($a, $b) => $b['repeat_probability'] <=> $a['repeat_probability']);

        $total = count($predictions);
        $avgProb = $total > 0
            ? round(collect($predictions)->avg('repeat_probability'), 1)
            : 0;
        $highRisk = collect($predictions)->whereIn('risk', ['High', 'Critical'])->count();
        $vip = collect($predictions)->where('is_vip', true)->count();

        $insightLines = $this->retentionInsightLines($predictions);
        if (! $aiAvailable) {
            array_unshift(
                $insightLines,
                'Reorder scores are estimated locally. Start retention-service on port 5002 for full AI predictions.'
            );
        }

        return response()->json([
            'customers' => $predictions,
            'ai_available' => $aiAvailable,
            'insights' => [
                'insights' => $insightLines,
                'segments' => collect($predictions)->groupBy('segment')->map->count()->all(),
                'summary' => [
                    'total_customers' => $total,
                    'avg_repeat_probability' => $avgProb,
                    'high_risk_count' => $highRisk,
                    'vip_count' => $vip,
                ],
            ],
        ]);
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     * @param  Collection<int, Review>  $customerReviews
     * @return array<string, mixed>
     */
    protected function retentionPayloadForCustomer(
        Collection $bookings,
        Collection $customerReviews,
        string $customerName,
    ): array {
        $completed = $bookings->where('status', 'completed');
        $canceled = $bookings->where('status', 'cancelled');

        $avgRating = $customerReviews->isNotEmpty()
            ? round($customerReviews->avg('rating'), 2)
            : ($completed->isNotEmpty() ? 4.0 : 3.5);

        $amounts = $completed->pluck('price')->filter()->map(fn ($p) => (float) $p);
        $avgSpending = $amounts->isNotEmpty() ? round($amounts->avg(), 2) : 50.0;

        $completedDates = $completed
            ->map(fn (Booking $b) => $b->completed_at ?? $b->scheduled_at)
            ->filter()
            ->sort()
            ->values();

        $orderFrequencyDays = 14.0;
        if ($completedDates->count() >= 2) {
            $gaps = [];
            for ($i = 1; $i < $completedDates->count(); $i++) {
                $gaps[] = abs($completedDates[$i - 1]->diffInDays($completedDates[$i]));
            }
            $orderFrequencyDays = max(0, round(array_sum($gaps) / count($gaps), 2));
        }

        $lastAt = $bookings
            ->map(fn (Booking $b) => $b->completed_at ?? $b->scheduled_at ?? $b->created_at)
            ->filter()
            ->max();

        $daysSince = 90.0;
        if ($lastAt) {
            $last = Carbon::parse($lastAt);
            $daysSince = max(0.0, (float) ($last->isFuture() ? 0 : $last->diffInDays(now())));
        }

        $latestReview = $customerReviews->sortByDesc('created_at')->first();
        $reviewText = $latestReview
            ? (trim((string) $latestReview->comment) ?: "Customer gave {$latestReview->rating} out of 5 stars.")
            : null;

        return [
            'customer_name' => $customerName,
            'avg_rating' => $avgRating,
            'total_orders' => $bookings->count(),
            'canceled_orders' => $canceled->count(),
            'avg_spending' => $avgSpending,
            'order_frequency_days' => max(0, (float) $orderFrequencyDays),
            'days_since_last_order' => max(0.0, (float) $daysSince),
            'app_sessions_30d' => min(30, $bookings->count() * 3),
            'delivery_satisfaction' => min(5.0, max(1.0, $avgRating)),
            'review_text' => $reviewText,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $predictions
     * @return list<string>
     */
    protected function retentionInsightLines(array $predictions): array
    {
        if ($predictions === []) {
            return ['No customer data to analyze yet.'];
        }

        $avg = round(collect($predictions)->avg('repeat_probability'), 1);
        $highRisk = collect($predictions)->whereIn('risk', ['High', 'Critical'])->count();
        $vip = collect($predictions)->where('is_vip', true)->count();
        $top = $predictions[0] ?? null;

        $lines = [
            "Average reorder probability across your customers is {$avg}%.",
        ];

        if ($highRisk > 0) {
            $lines[] = "{$highRisk} customer(s) are at high churn risk — consider follow-up or offers.";
        }

        if ($vip > 0) {
            $lines[] = "{$vip} VIP customer(s) show strong loyalty and repeat booking potential.";
        }

        if ($top) {
            $lines[] = "Highest retention: {$top['customer']} ({$top['repeat_probability']}% likely to reorder).";
        }

        return $lines;
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
