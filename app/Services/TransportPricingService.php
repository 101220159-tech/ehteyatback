<?php

namespace App\Services;

class TransportPricingService
{
    /** @return array{taxi: float, currency: string, breakdown: array} */
    public function estimateTaxi(
        float $distanceKm,
        float $durationMinutes,
        string $vehicleType = 'standard',
    ): array {
        $c    = config('transport.taxi');
        $mult = (float) ($c['vehicle_multiplier'][$vehicleType] ?? $c['vehicle_multiplier']['standard'] ?? 1.0);

        $base = (float) $c['base_fee'];
        $dist = (float) $c['per_km'] * $distanceKm;
        $time = (float) $c['per_minute'] * $durationMinutes;

        $subtotal = $base + $dist + $time;
        $total    = round($subtotal * $mult, 2);

        $out = [
            'estimated_price' => $total,
            'currency'        => 'USD',
            'breakdown'       => [
                'base_fee'            => $base,
                'distance_component'  => round($dist * $mult, 2),
                'time_component'      => round($time * $mult, 2),
                'vehicle_multiplier'  => $mult,
            ],
        ];

        return array_merge($out, $this->commissionSplit($total));
    }

    /**
     * @return array{shipping_price: float, currency: string, suggested_vehicle?: string, warning?: string|null, breakdown: array}
     */
    public function estimateDelivery(
        float $distanceKm,
        float $durationMinutes,
        string $vehicleType,
        float $packageWeightKg,
        int $quantity,
        bool $fragile,
    ): array {
        $c           = config('transport.delivery');
        $maxBike     = (float) $c['motorcycle_max_kg'];
        $warning     = null;
        $suggested   = null;

        if ($vehicleType === 'motorcycle' && $packageWeightKg > $maxBike) {
            $warning   = 'Package weight exceeds motorcycle limit ('.$maxBike.' kg). Please select car or truck.';
            $suggested = $packageWeightKg > 80 ? 'truck' : 'car';
        }

        $bases = $c['base_fee'];
        $pkm   = $c['per_km'];
        if (! isset($bases[$vehicleType])) {
            $vehicleType = 'car';
        }

        $base  = (float) $bases[$vehicleType];
        $perKm = (float) $pkm[$vehicleType] * $distanceKm;

        $extraKg = max(0, $packageWeightKg - 5);
        $weightSurcharge = $extraKg * (float) $c['weight_surcharge_per_kg'] * max(1, $quantity);

        $subtotal = $base + $perKm + $weightSurcharge;
        if ($fragile) {
            $subtotal *= (float) $c['fragile_multiplier'];
        }

        $total = round($subtotal, 2);

        $out = [
            'shipping_price' => $total,
            'currency'       => 'USD',
            'suggested_vehicle' => $suggested,
            'warning'        => $warning,
            'breakdown'      => [
                'base'              => $base,
                'distance_component'=> round((float) $pkm[$vehicleType] * $distanceKm, 2),
                'weight_surcharge' => round($weightSurcharge, 2),
                'fragile_applied'  => $fragile,
            ],
        ];

        return array_merge($out, $this->commissionSplit($total));
    }

    /**
     * Optional revenue split when TRANSPORT_PLATFORM_COMMISSION_PERCENT is set (> 0, ≤ 100).
     *
     * @return array<string, float|int|null>
     */
    protected function commissionSplit(float $customerTotal): array
    {
        $pct = (float) config('transport.platform.commission_percent', 0);
        if ($pct <= 0 || $pct > 100) {
            return [
                'commission_percent' => null,
                'platform_fee_estimate' => null,
                'partner_payout_estimate' => null,
            ];
        }

        $fee = round($customerTotal * ($pct / 100), 2);

        return [
            'commission_percent' => round($pct, 2),
            'platform_fee_estimate' => $fee,
            'partner_payout_estimate' => round($customerTotal - $fee, 2),
        ];
    }

    public function motorcycleMaxKg(): float
    {
        return (float) config('transport.delivery.motorcycle_max_kg');
    }

    /** Suggest delivery vehicle by weight only */
    public function suggestDeliveryVehicle(float $packageWeightKg): string
    {
        $max = $this->motorcycleMaxKg();
        if ($packageWeightKg <= $max) {
            return 'motorcycle';
        }
        if ($packageWeightKg <= 80) {
            return 'car';
        }

        return 'truck';
    }
}
