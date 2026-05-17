<?php

namespace App\Services;

/**
 * Fallback reorder probability when retention-service (port 5002) is offline.
 */
class LocalRetentionPredictor
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function predict(array $payload): array
    {
        $avgRating = (float) ($payload['avg_rating'] ?? 4.0);
        $total = max(0, (int) ($payload['total_orders'] ?? 0));
        $canceled = (int) ($payload['canceled_orders'] ?? 0);
        $daysSince = (float) ($payload['days_since_last_order'] ?? 30.0);
        $freq = (float) ($payload['order_frequency_days'] ?? 14.0);
        $avgSpending = (float) ($payload['avg_spending'] ?? 50.0);
        $name = (string) ($payload['customer_name'] ?? 'Customer');

        $proba = 48.0;
        $proba += ($avgRating - 3.0) * 14.0;
        $proba += min(22.0, $total * 5.0);
        $proba -= min(28.0, $daysSince * 0.4);
        if ($total > 0) {
            $proba -= min(18.0, ($canceled / $total) * 22.0);
        }
        if ($freq > 0 && $freq <= 21.0) {
            $proba += 8.0;
        }
        if ($total >= 3) {
            $proba += 6.0;
        }

        $proba = round(max(5.0, min(95.0, $proba)), 1);

        $risk = match (true) {
            $proba >= 72 => 'Low',
            $proba >= 48 => 'Medium',
            $proba >= 28 => 'High',
            default => 'Critical',
        };

        $loyalty = match (true) {
            $proba >= 85 => 'VIP',
            $proba >= 65 => 'High',
            $proba >= 45 => 'Medium',
            default => 'Low',
        };

        return [
            'customer_name' => $name,
            'repeat_order_probability' => $proba,
            'loyalty_score' => round($proba * 0.9, 1),
            'sentiment_score' => round(($avgRating - 3) / 2, 3),
            'churn_risk' => $risk,
            'loyalty_level' => $loyalty,
            'last_order_date' => null,
            'avg_rating' => $avgRating,
            'total_orders' => $total,
            'is_vip' => $proba >= 85,
            'estimated_ltv' => round($avgSpending * max(1, $total), 2),
            'segment' => 'Estimated (AI offline)',
        ];
    }
}
