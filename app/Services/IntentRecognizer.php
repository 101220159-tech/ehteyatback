<?php

namespace App\Services;

class IntentRecognizer
{
    /** @var array<string, list<string>> */
    protected array $serviceKeywords = [
        'plumbing' => [
            'water heater', 'water leak', 'clogged', 'plumbing', 'plumber', 'toilet', 'faucet',
            'drain', 'leaking', 'leak', 'sink', 'pipe',
        ],
        'electrical' => [
            'power outage', 'flickering', 'electrical', 'electrician', 'circuit', 'wiring',
            'socket', 'switch', 'fuse', 'wire', 'light',
        ],
        'cleaning' => [
            'housekeeping', 'deep clean', 'cleaning', 'cleaner', 'janitor', 'maid', 'tidy', 'dust', 'clean',
        ],
        'painting' => [
            'paint job', 'repaint', 'painting', 'painter', 'exterior', 'interior', 'color', 'wall', 'paint',
        ],
        'ac_repair' => [
            'air conditioner', 'ac repair', 'refrigerant', 'compressor', 'aircon', 'hvac', 'cooling', 'ac unit', 'ac',
        ],
        'carpentry' => [
            'cabinetry', 'carpentry', 'carpenter', 'flooring', 'furniture', 'cabinet', 'window', 'door', 'wood',
        ],
        'gardening' => [
            'landscaping', 'gardening', 'gardener', 'mowing', 'grass', 'plants', 'trees', 'lawn', 'garden',
        ],
        'moving' => [
            'relocation', 'movers', 'moving', 'packing', 'transport', 'delivery', 'mover', 'move',
        ],
    ];

    protected array $urgentKeywords = ['emergency', 'urgent', 'asap', 'immediately', 'right now', 'quick', 'fast', 'now'];

    protected array $budgetKeywords = ['cheap', 'affordable', 'budget', 'low cost', 'inexpensive', 'cheapest'];

    protected array $premiumKeywords = ['best', 'top rated', 'expert', 'professional', 'high quality', 'premium'];

    protected array $lebanonAreas = [
        'beirut', 'hamra', 'ashrafieh', 'verdun', 'gemmayze', 'mar mikhael',
        'tripoli', 'jounieh', 'byblos', 'batroun', 'zahle', 'saida', 'tyre',
        'baabda', 'metn', 'chouf', 'keserwan', 'dbayeh', 'jal el dib',
    ];

    public function understand(string $message): array
    {
        $lowerMessage = strtolower($message);

        $latestPos = $this->latestKeywordPositionsPerService($lowerMessage);

        if ($latestPos === []) {
            $detectedServices = [];
            $primaryService = 'general';
        } else {
            arsort($latestPos);
            $detectedServices = array_keys($latestPos);
            $primaryService = $detectedServices[0];
        }

        $isUrgent = false;
        foreach ($this->urgentKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                $isUrgent = true;
                break;
            }
        }

        $pricePreference = 'any';
        foreach ($this->budgetKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                $pricePreference = 'budget';
                break;
            }
        }
        if ($pricePreference === 'any') {
            foreach ($this->premiumKeywords as $keyword) {
                if (str_contains($lowerMessage, $keyword)) {
                    $pricePreference = 'premium';
                    break;
                }
            }
        }

        $location = null;
        foreach ($this->lebanonAreas as $area) {
            if (str_contains($lowerMessage, $area)) {
                $location = ucwords(str_replace('_', ' ', $area));

                break;
            }
        }

        return [
            'service_type' => $primaryService,
            'detected_services' => $detectedServices,
            'is_urgent' => $isUrgent,
            'price_preference' => $pricePreference,
            'location' => $location,
            'confidence' => $detectedServices !== [] ? 0.9 : 0.35,
        ];
    }

    /**
     * @return array<string, int> service key => index of match end (latest occurrence wins for primary)
     */
    protected function latestKeywordPositionsPerService(string $lowerMessage): array
    {
        $latestPos = [];

        foreach ($this->serviceKeywords as $service => $keywords) {
            $sorted = $keywords;
            usort($sorted, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

            $best = -1;
            foreach ($sorted as $keyword) {
                $kw = strtolower($keyword);
                if (! $this->messageContainsKeyword($lowerMessage, $kw)) {
                    continue;
                }
                $pos = $this->lastOccurrenceEndOffset($lowerMessage, $kw);
                if ($pos > $best) {
                    $best = $pos;
                }
            }

            if ($best >= 0) {
                $latestPos[$service] = $best;
            }
        }

        return $latestPos;
    }

    protected function messageContainsKeyword(string $haystack, string $keyword): bool
    {
        if ($keyword === 'ac') {
            return (bool) preg_match('/\bac\b/u', $haystack);
        }

        if (strlen($keyword) <= 2) {
            return (bool) preg_match('/\b'.preg_quote($keyword, '/').'\b/u', $haystack);
        }

        return str_contains($haystack, $keyword);
    }

    protected function lastOccurrenceEndOffset(string $haystack, string $keyword): int
    {
        if ($keyword === 'ac') {
            $end = -1;
            if (preg_match_all('/\bac\b/u', $haystack, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $hit) {
                    $end = max($end, (int) $hit[1] + strlen($hit[0]));
                }
            }

            return $end;
        }

        if (strlen($keyword) <= 2) {
            $end = -1;
            $re = '/\b'.preg_quote($keyword, '/').'\b/u';
            if (preg_match_all($re, $haystack, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $hit) {
                    $end = max($end, (int) $hit[1] + strlen($hit[0]));
                }
            }

            return $end;
        }

        $pos = -1;
        $offset = 0;
        $len = mb_strlen($keyword);
        while (($found = mb_strpos($haystack, $keyword, $offset)) !== false) {
            $pos = $found + $len;
            $offset = $found + 1;
        }

        return $pos;
    }
}
