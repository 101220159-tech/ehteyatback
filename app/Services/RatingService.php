<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\Review;

class RatingService
{
    public function recalculate(Provider $provider): void
    {
        $stats = Review::query()
            ->where('provider_id', $provider->id)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as cnt')
            ->first();

        $provider->update([
            'rating_avg' => round((float) ($stats->avg_rating ?? 0), 2),
            'total_reviews' => (int) ($stats->cnt ?? 0),
        ]);
    }

    public function recalculateAll(): int
    {
        $ids = Provider::query()->pluck('id');
        foreach ($ids as $id) {
            $this->recalculate(Provider::query()->findOrFail($id));
        }

        return $ids->count();
    }
}
