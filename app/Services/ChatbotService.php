<?php

namespace App\Services;

use App\Models\ChatbotMessage;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ChatbotService
{
    public function __construct(
        protected LlamaService $llama,
        protected GooglePlacesService $googlePlaces,
        protected IntentRecognizer $intentRecognizer
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     * @return array{response: string, recommendations: array<int, array<string, mixed>>, google_places: array<int, array<string, mixed>>, intent: array<string, mixed>}
     */
    public function processMessage(
        string $message,
        ?string $userId = null,
        ?float $latitude = null,
        ?float $longitude = null,
        array $conversationHistory = [],
        ?string $conversationId = null,
    ): array {
        $intentSource = $this->buildMergedUserIntentSource($conversationId) ?? $message;
        $intent = $this->intentRecognizer->understand($intentSource);
        $turnIntent = $this->intentRecognizer->understand($message);
        $intent = $this->mergeChatbotIntent($intent, $turnIntent, $conversationId);

        $providers = $this->findProviders($intent, $latitude, $longitude);
        $enriched = $this->enrichWithGoogleReviews($providers);
        $recommendations = $this->serializeRecommendations($enriched);
        $googlePlaces = $this->maybeFetchGooglePlacesForChat($intent, $message, $enriched, $latitude, $longitude);
        $llamaResponse = $this->getLlamaResponse($message, $recommendations, $intent, $conversationHistory, $googlePlaces);

        return [
            'response' => $llamaResponse,
            'recommendations' => $recommendations,
            'google_places' => $googlePlaces,
            'intent' => $intent,
        ];
    }

    /**
     * Public Google Maps listings (Places Text Search). Not NexVex bookable providers.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function maybeFetchGooglePlacesForChat(
        array $intent,
        string $message,
        Collection $enrichedProviders,
        ?float $latitude,
        ?float $longitude
    ): array {
        if (! filter_var(env('CHATBOT_GOOGLE_TEXT_SEARCH', false), FILTER_VALIDATE_BOOLEAN)) {
            return [];
        }

        $query = $this->buildGoogleTextSearchQuery($intent, $message);
        if ($query === '') {
            return [];
        }

        $max = max(1, min(8, (int) env('CHATBOT_GOOGLE_TEXT_MAX', 5)));
        $pool = $max + $enrichedProviders->count();
        $raw = $this->googlePlaces->textSearch($query, $latitude, $longitude, min(10, $pool));

        $linkedPlaceIds = $enrichedProviders
            ->pluck('google_place_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->all();

        $out = [];
        foreach ($raw as $row) {
            $pid = (string) ($row['place_id'] ?? '');
            if ($pid === '' || in_array($pid, $linkedPlaceIds, true)) {
                continue;
            }
            $out[] = [
                'source' => 'google_maps',
                'place_id' => $pid,
                'name' => (string) ($row['name'] ?? ''),
                'google_rating' => $row['rating'] ?? null,
                'google_review_count' => (int) ($row['user_ratings_total'] ?? 0),
                'location' => (string) ($row['formatted_address'] ?? ''),
                'maps_url' => (string) ($row['maps_url'] ?? ''),
            ];
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    protected function buildGoogleTextSearchQuery(array $intent, string $message): string
    {
        $serviceType = $intent['service_type'] ?? 'general';
        $parts = [];
        if ($serviceType !== 'general') {
            $parts[] = str_replace('_', ' ', $serviceType);
        }
        if (! empty($intent['location'])) {
            $parts[] = (string) $intent['location'];
        }
        $fromIntent = trim(implode(' ', $parts));
        if ($fromIntent !== '') {
            return Str::limit($fromIntent.' Lebanon', 200);
        }

        return Str::limit(trim($message), 200);
    }

    /**
     * Combine merged conversation text intent with the current user line. Latest explicit service in the
     * current message wins when present; otherwise keep merged. If still "general", reuse an earlier
     * user line in the thread (e.g. location-only follow-up).
     *
     * @param  array<string, mixed>  $mergedIntent
     * @param  array<string, mixed>  $turnIntent
     * @return array<string, mixed>
     */
    protected function mergeChatbotIntent(array $mergedIntent, array $turnIntent, ?string $conversationId): array
    {
        if (($turnIntent['service_type'] ?? 'general') !== 'general') {
            $mergedIntent['service_type'] = $turnIntent['service_type'];
            $mergedIntent['detected_services'] = $turnIntent['detected_services'];
        } elseif (($mergedIntent['service_type'] ?? 'general') === 'general' && $conversationId) {
            $prior = $this->inferServiceTypeFromPriorUserMessages($conversationId);
            if ($prior !== null) {
                $mergedIntent['service_type'] = $prior;
                $mergedIntent['detected_services'] = [$prior];
            }
        }

        if (empty($mergedIntent['location']) && ! empty($turnIntent['location'])) {
            $mergedIntent['location'] = $turnIntent['location'];
        }

        return $mergedIntent;
    }

    protected function inferServiceTypeFromPriorUserMessages(string $conversationId): ?string
    {
        $rows = ChatbotMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('sender', 'user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->pluck('message');

        foreach ($rows->slice(1) as $text) {
            $i = $this->intentRecognizer->understand(trim((string) $text));
            if (($i['service_type'] ?? 'general') !== 'general') {
                return $i['service_type'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    protected function findProviders(array $intent, ?float $latitude, ?float $longitude): Collection
    {
        $query = Provider::query()
            ->with(['user:id,name,email,address', 'services:id,name,category_id', 'services.category:id,name', 'reviews', 'zones:id,name'])
            ->where('is_active', true)
            ->where('is_verified', true);

        $serviceType = $intent['service_type'] ?? 'general';
        if ($serviceType !== 'general') {
            $query->whereHas('services', function (Builder $q) use ($serviceType) {
                $categoryName = $this->categoryNameForChatbotIntent($serviceType);
                if ($categoryName !== null) {
                    $q->whereHas('category', fn (Builder $cq) => $cq->where('name', $categoryName));

                    return;
                }
                $patterns = $this->serviceLikePatterns($serviceType);
                if ($patterns === []) {
                    return;
                }
                $q->where(function (Builder $inner) use ($patterns) {
                    foreach ($patterns as $i => $pattern) {
                        if ($i === 0) {
                            $inner->where('name', 'LIKE', $pattern);
                        } else {
                            $inner->orWhere('name', 'LIKE', $pattern);
                        }
                    }
                });
            });
        }

        if (! empty($intent['location'])) {
            $locPatterns = $this->locationMatchPatterns((string) $intent['location']);
            if ($locPatterns !== []) {
                $query->where(function (Builder $q) use ($locPatterns) {
                    foreach ($locPatterns as $pat) {
                        $q->orWhereHas('zones', fn (Builder $zq) => $zq->where('name', 'LIKE', $pat));
                    }
                    foreach ($locPatterns as $pat) {
                        $q->orWhereHas('user', fn (Builder $uq) => $uq->where('address', 'LIKE', $pat));
                    }
                });
            }
        }

        if (($intent['price_preference'] ?? 'any') === 'premium') {
            $query->where('rating_avg', '>=', 4.0);
        }

        $hasCoords = $latitude !== null && $longitude !== null
            && is_finite($latitude) && is_finite($longitude);

        if ($hasCoords) {
            $query->orderByDesc('rating_avg');
            $pool = max(5, min(80, (int) env('CHATBOT_DISTANCE_CANDIDATE_LIMIT', 40)));
            $providers = $query->limit($pool)->get();

            return $this->rankProvidersByDistanceThenRating($providers, $latitude, $longitude);
        }

        $query->orderByDesc('rating_avg');

        return $query->limit(5)->get();
    }

    /**
     * @return Collection<int, Provider>
     */
    protected function rankProvidersByDistanceThenRating(Collection $providers, float $customerLat, float $customerLon): Collection
    {
        return $providers->map(function (Provider $p) use ($customerLat, $customerLon) {
            $plat = $p->getAttribute('latitude');
            $plon = $p->getAttribute('longitude');
            if ($plat === null || $plon === null || $plat === '' || $plon === '') {
                $p->distance_km = null;
            } else {
                $p->distance_km = round($this->haversineKm($customerLat, $customerLon, (float) $plat, (float) $plon), 1);
            }

            return $p;
        })->sort(function (Provider $a, Provider $b) {
            $da = $a->distance_km ?? null;
            $db = $b->distance_km ?? null;
            $ratingCmp = ((float) ($b->rating_avg ?? 0)) <=> ((float) ($a->rating_avg ?? 0));

            if ($da === null && $db === null) {
                return $ratingCmp;
            }
            if ($da === null) {
                return 1;
            }
            if ($db === null) {
                return -1;
            }
            if (abs($da - $db) < 0.05) {
                return $ratingCmp;
            }

            return $da <=> $db;
        })->values()->take(5);
    }

    protected function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $h = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($h), sqrt(1 - $h));
    }

    protected function buildMergedUserIntentSource(?string $conversationId): ?string
    {
        if (! $conversationId) {
            return null;
        }

        $max = max(2, min(8, (int) env('CHATBOT_INTENT_USER_MESSAGES_MAX', 4)));
        $msgs = ChatbotMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('sender', 'user')
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('message');

        if ($msgs->isEmpty()) {
            return null;
        }

        $merged = $msgs->slice(-$max)->values()
            ->map(fn ($m) => trim((string) $m))
            ->filter()
            ->implode(' ');

        if ($merged === '') {
            return null;
        }

        return Str::limit($merged, 2000);
    }

    /**
     * NexVex catalog ties each {@see Service} to a {@see ServiceCategory}; matching the category
     * finds all plumbers/electricians even when service titles are "Pipe Repair" not "Plumbing".
     */
    protected function categoryNameForChatbotIntent(string $serviceType): ?string
    {
        return match ($serviceType) {
            'plumbing' => 'Plumbing',
            'electrical' => 'Electrical',
            'cleaning' => 'Cleaning',
            'painting' => 'Painting',
            'ac_repair' => 'AC Repair',
            'carpentry' => 'Carpentry',
            'gardening' => 'Gardening',
            'moving' => 'Moving',
            default => null,
        };
    }

    /**
     * Broaden "Beirut" to districts often stored without the word "Beirut" on profiles.
     *
     * @return array<int, string> SQL LIKE patterns including % wildcards
     */
    protected function locationMatchPatterns(string $location): array
    {
        $key = strtolower(trim($location));
        $map = [
            'beirut' => [
                '%Beirut%', '%Hamra%', '%Ashrafieh%', '%Achrafieh%', '%Verdun%',
                '%Gemmayze%', '%Gemmayzé%', '%Mar Mikhael%', '%Rawche%', '%Raouché%', '%Rawshe%',
            ],
            'tripoli' => ['%Tripoli%', '%Trablos%'],
            'jounieh' => ['%Jounieh%'],
            'byblos' => ['%Byblos%', '%Jbail%', '%Jbeil%'],
            'zahle' => ['%Zahle%', '%Zahlé%'],
            'saida' => ['%Saida%', '%Sidon%'],
            'tyre' => ['%Tyre%', '%Sour%'],
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        return ['%'.$location.'%'];
    }

    /**
     * Fallback when {@see categoryNameForChatbotIntent} is null — matches {@see Service} names.
     *
     * @return array<int, string>
     */
    protected function serviceLikePatterns(string $serviceType): array
    {
        $map = [
            'plumbing' => ['%plumb%', '%pipe%', '%drain%', '%toilet%', '%faucet%', '%leak%', '%sink%', '%water heater%'],
            'electrical' => ['%electr%', '%light%', '%outlet%', '%circuit%', '%wire%', '%wiring%', '%panel%'],
            'cleaning' => ['%clean%'],
            'painting' => ['%paint%', '%wall%'],
            'ac_repair' => ['%AC%', '%air%', '%hvac%', '%cool%'],
            'carpentry' => ['%carpent%', '%cabinet%', '%door%', '%furniture%', '%wood%', '%shel%'],
            'gardening' => ['%garden%', '%landscap%', '%lawn%', '%tree%', '%mow%'],
            'moving' => ['%move%', '%pack%', '%relocat%', '%mover%', '%deliver%'],
        ];

        return $map[$serviceType] ?? ['%'.str_replace('_', '%', $serviceType).'%'];
    }

    protected function enrichWithGoogleReviews(Collection $providers): Collection
    {
        foreach ($providers as $provider) {
            $provider->platform_rating = round((float) ($provider->reviews->avg('rating') ?? 0), 2);
            $provider->platform_review_count = $provider->reviews->count();

            $placeId = $provider->getAttribute('google_place_id');
            if ($placeId) {
                $googleData = $this->googlePlaces->getPlaceDetails((string) $placeId);
                if ($googleData) {
                    $provider->google_rating = $googleData['rating'];
                    $provider->google_review_count = $googleData['total_ratings'];
                    $provider->google_reviews = $googleData['reviews'];
                }
            }
        }

        return $providers;
    }

    /**
     * @param  array<int, array<string, mixed>>  $recommendations
     * @param  array<int, array<string, mixed>>  $googlePlaces
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     */
    protected function getLlamaResponse(
        string $message,
        array $recommendations,
        array $intent,
        array $conversationHistory = [],
        array $googlePlaces = []
    ): string {
        if (! $this->llama->isRunning()) {
            return $this->fallbackResponse($intent, $recommendations, $googlePlaces);
        }

        $context = [
            'recommendations' => array_slice($recommendations, 0, 5),
            'google_places' => array_slice($googlePlaces, 0, 5),
            'intent_summary' => $this->formatIntentForPrompt($intent),
        ];
        $response = $this->llama->generateResponse($message, $context, $conversationHistory);

        return $response ?? $this->fallbackResponse($intent, $recommendations, $googlePlaces);
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    protected function formatIntentForPrompt(array $intent): string
    {
        $parts = [];
        $service = str_replace('_', ' ', (string) ($intent['service_type'] ?? 'general'));
        $parts[] = 'service: '.$service;
        if (! empty($intent['location'])) {
            $parts[] = 'area: '.$intent['location'];
        }
        if (! empty($intent['is_urgent'])) {
            $parts[] = 'urgent: yes';
        }
        if (($intent['price_preference'] ?? 'any') !== 'any') {
            $parts[] = 'price: '.$intent['price_preference'];
        }

        return implode('; ', $parts);
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  array<int, array<string, mixed>>  $recommendations
     * @param  array<int, array<string, mixed>>  $googlePlaces
     */
    protected function fallbackResponse(array $intent, array $recommendations, array $googlePlaces = []): string
    {
        $serviceLabel = str_replace('_', ' ', (string) ($intent['service_type'] ?? 'general'));

        if ($recommendations === [] && $googlePlaces === []) {
            return "Sorry, I couldn't find any {$serviceLabel} matches on NexVex or Google Maps for that request. Try rephrasing or browse the search page.";
        }

        if ($recommendations === [] && $googlePlaces !== []) {
            $g = $googlePlaces[0];

            return "I didn't find NexVex partners for that yet. Here are nearby Google Maps listings for ideas (booking is only through NexVex when we list someone).\n\n📍 {$g['name']}"
                .(isset($g['google_rating']) && $g['google_rating'] !== null ? ' — '.number_format((float) $g['google_rating'], 1).'⭐' : '')
                ."\n{$g['location']}\n\nSee the cards below to open Maps.";
        }

        $top = $recommendations[0];
        $suffix = '';
        if ($googlePlaces !== []) {
            $suffix = "\n\nI've also added a few Google Maps results below for comparison — those aren't NexVex bookings unless they're on our platform.";
        }

        return "I found ".count($recommendations)." NexVex {$serviceLabel} provider(s)!\n\n🏆 Top: {$top['name']} — "
            .number_format((float) $top['platform_rating'], 1).'⭐ ('.(int) $top['platform_review_count']." reviews)\n📍 {$top['location']}\n\nWould you like to view their profile?".$suffix;
    }

    protected function serializeRecommendations(Collection $providers): array
    {
        $out = [];
        foreach ($providers as $provider) {
            $user = $provider->user;
            $name = $user?->name ?? 'Provider';
            $zones = $provider->relationLoaded('zones') ? $provider->zones->pluck('name')->filter()->implode(', ') : '';
            $location = $zones !== '' ? $zones : (string) ($user?->address ?? 'Lebanon');

            $row = [
                'source' => 'nexvex',
                'id' => $provider->id,
                'name' => $name,
                'platform_rating' => (float) ($provider->platform_rating ?? $provider->rating_avg ?? 0),
                'platform_review_count' => (int) ($provider->platform_review_count ?? 0),
                'google_rating' => $provider->google_rating ?? null,
                'google_review_count' => $provider->google_review_count ?? null,
                'location' => $location,
            ];

            if (isset($provider->distance_km) && $provider->distance_km !== null) {
                $row['distance_km'] = (float) $provider->distance_km;
            }

            $out[] = $row;
        }

        return $out;
    }
}
