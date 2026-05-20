<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Zone;
use Illuminate\Support\Collection;

class ZoneDetectionService
{
    /**
     * Find all zones whose areas contain the given coordinates.
     * Uses the ray-casting point-in-polygon algorithm.
     *
     * @return Collection<Zone>
     */
    public function detectZones(float $lat, float $lng): Collection
    {
        $areas = Area::query()->with('zone')->get();

        $matchedZoneIds = [];

        foreach ($areas as $area) {
            $polygon = $area->coordinates; // [{lat: x, lng: y}, ...]
            if (! is_array($polygon) || count($polygon) < 3) {
                continue;
            }

            if ($this->pointInPolygon($lat, $lng, $polygon)) {
                $matchedZoneIds[] = $area->zone_id;
            }
        }

        if (empty($matchedZoneIds)) {
            return collect();
        }

        return Zone::query()
            ->whereIn('id', array_unique($matchedZoneIds))
            ->get();
    }

    /**
     * Return the first matched zone ID, or null if none.
     */
    public function detectZoneId(float $lat, float $lng): ?string
    {
        return $this->detectZones($lat, $lng)->first()?->id;
    }

    /**
     * Ray-casting algorithm — returns true if ($lat, $lng) is inside $polygon.
     * Polygon is an array of ['lat' => x, 'lng' => y] or ['latitude' => x, 'longitude' => y].
     */
    public function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $inside = false;
        $n      = count($polygon);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = (float) ($polygon[$i]['lng'] ?? $polygon[$i]['longitude'] ?? 0);
            $yi = (float) ($polygon[$i]['lat'] ?? $polygon[$i]['latitude'] ?? 0);
            $xj = (float) ($polygon[$j]['lng'] ?? $polygon[$j]['longitude'] ?? 0);
            $yj = (float) ($polygon[$j]['lat'] ?? $polygon[$j]['latitude'] ?? 0);

            if ((($yi > $lat) !== ($yj > $lat)) &&
                ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
