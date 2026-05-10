<?php

namespace Tests\Unit;

use App\Services\IntentRecognizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IntentRecognizerTest extends TestCase
{
    private IntentRecognizer $recognizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recognizer = new IntentRecognizer;
    }

    /**
     * Phase 1 — 24 structured detection checks (8 services + 3 urgency + 2 price + 11 locations).
     *
     * @return iterable<string, array{0: string, 1: string, 2: bool, 3: string, 4: ?string}>
     */
    public static function phaseOneDetectionCases(): iterable
    {
        // ── 8 service types ─────────────────────────────────────────────
        yield 'service_plumbing' => ['The kitchen sink is leaking and the faucet drips', 'plumbing', false, 'any', null];
        yield 'service_electrical' => ['We have a power outage and need an electrician for the circuit', 'electrical', false, 'any', null];
        yield 'service_cleaning' => ['Looking for deep cleaning and a maid this week', 'cleaning', false, 'any', null];
        yield 'service_painting' => ['Interior painting job for the living room walls', 'painting', false, 'any', null];
        yield 'service_ac_repair' => ['The ac unit stopped cooling, need ac repair', 'ac_repair', false, 'any', null];
        yield 'service_carpentry' => ['Custom cabinet and door repair by a carpenter', 'carpentry', false, 'any', null];
        yield 'service_gardening' => ['Lawn mowing and garden maintenance help', 'gardening', false, 'any', null];
        yield 'service_moving' => ['Packing and movers for our relocation next month', 'moving', false, 'any', null];

        // ── 3 urgency variants (with a neutral service keyword to keep plumbing primary) ──
        yield 'urgency_emergency' => ['Emergency plumbing leak in the bathroom pipe', 'plumbing', true, 'any', null];
        yield 'urgency_urgent' => ['Urgent electrical fault with flickering lights', 'electrical', true, 'any', null];
        yield 'urgency_asap' => ['Need a cleaner asap for the apartment', 'cleaning', true, 'any', null];

        // ── 2 price preferences ───────────────────────────────────────────
        yield 'price_budget' => ['Budget plumber for a small toilet repair', 'plumbing', false, 'budget', null];
        yield 'price_premium' => ['Premium professional painter for high quality walls', 'painting', false, 'premium', null];

        // ── 11 Lebanon location extractions ───────────────────────────────
        yield 'loc_beirut' => ['Electrician needed in beirut tomorrow', 'electrical', false, 'any', 'Beirut'];
        yield 'loc_hamra' => ['House cleaning service in hamra please', 'cleaning', false, 'any', 'Hamra'];
        yield 'loc_ashrafieh' => ['Painter for ashrafieh apartment', 'painting', false, 'any', 'Ashrafieh'];
        yield 'loc_verdun' => ['AC repair in verdun area', 'ac_repair', false, 'any', 'Verdun'];
        yield 'loc_gemmayze' => ['Moving help in gemmayze', 'moving', false, 'any', 'Gemmayze'];
        yield 'loc_mar_mikhael' => ['Carpenter near mar mikhael', 'carpentry', false, 'any', 'Mar Mikhael'];
        yield 'loc_tripoli' => ['Gardening in tripoli', 'gardening', false, 'any', 'Tripoli'];
        yield 'loc_jounieh' => ['Plumber in jounieh', 'plumbing', false, 'any', 'Jounieh'];
        yield 'loc_byblos' => ['Cleaning in byblos', 'cleaning', false, 'any', 'Byblos'];
        yield 'loc_batroun' => ['Painting job batroun coast', 'painting', false, 'any', 'Batroun'];
        yield 'loc_zahle' => ['Electrical work in zahle', 'electrical', false, 'any', 'Zahle'];
    }

    #[DataProvider('phaseOneDetectionCases')]
    public function test_phase_one_intent_detection(
        string $message,
        string $expectedService,
        bool $expectedUrgent,
        string $expectedPrice,
        ?string $expectedLocation,
    ): void {
        $intent = $this->recognizer->understand($message);

        $this->assertSame($expectedService, $intent['service_type'], 'service_type mismatch');
        $this->assertContains($expectedService, $intent['detected_services'], 'detected_services should include primary');
        $this->assertSame($expectedUrgent, $intent['is_urgent'], 'is_urgent mismatch');
        $this->assertSame($expectedPrice, $intent['price_preference'], 'price_preference mismatch');
        $this->assertSame($expectedLocation, $intent['location'], 'location mismatch');
        $this->assertIsFloat($intent['confidence']);
        $this->assertGreaterThan(0.0, $intent['confidence']);
    }

    public function test_general_service_when_no_keywords(): void
    {
        $intent = $this->recognizer->understand('Hello there how are you');

        $this->assertSame('general', $intent['service_type']);
        $this->assertSame([], $intent['detected_services']);
        $this->assertFalse($intent['is_urgent']);
        $this->assertSame('any', $intent['price_preference']);
        $this->assertNull($intent['location']);
        $this->assertSame(0.35, $intent['confidence']);
    }

    public function test_premium_wins_over_budget_when_both_present(): void
    {
        // Budget branch runs first in implementation; first match wins.
        $intent = $this->recognizer->understand('Budget cheap cleaner but also premium quality');

        $this->assertSame('budget', $intent['price_preference']);
    }

    public function test_ac_keyword_requires_word_boundary(): void
    {
        $noBoundary = $this->recognizer->understand('stack overflow'); // contains "ac" substring inside word
        $this->assertSame('general', $noBoundary['service_type']);

        $boundary = $this->recognizer->understand('need ac service today');
        $this->assertSame('ac_repair', $boundary['service_type']);
    }
}
