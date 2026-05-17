<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Customer retention / reorder probability (C:\Users\HP\ai\retention-service — port 5002).
 */
class RetentionService
{
    protected function client()
    {
        return Http::timeout((int) config('services.retention.timeout', 120))
            ->acceptJson()
            ->withHeaders([
                'X-API-Key' => config('services.retention.api_key'),
            ]);
    }

    protected function baseUrl(): string
    {
        return rtrim(config('services.retention.url'), '/');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function predict(array $payload): array
    {
        $response = $this->client()->post($this->baseUrl().'/predict-retention', $payload);

        if ($response->failed()) {
            Log::error('Retention predict error', ['body' => $response->body()]);
            throw new \RuntimeException($this->errorMessage($response));
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function insights(): array
    {
        $response = $this->client()->get($this->baseUrl().'/insights');

        if ($response->failed()) {
            Log::error('Retention insights error', ['body' => $response->body()]);
            throw new \RuntimeException($this->errorMessage($response, 'Retention insights unavailable.'));
        }

        return $response->json();
    }

    protected function errorMessage($response, string $fallback = 'Retention service unavailable. Start retention-service on port 5002.'): string
    {
        $detail = $response->json('detail');
        if (is_array($detail)) {
            return collect($detail)
                ->map(fn ($item) => is_array($item) ? ($item['msg'] ?? json_encode($item)) : (string) $item)
                ->implode('; ');
        }

        return (string) ($detail ?? $response->json('error') ?? $fallback);
    }
}
