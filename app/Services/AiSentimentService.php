<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Calls the Python Flask AI microservice (C:\Users\HP\ai\ai-service, port 5001).
 */
class AiSentimentService
{
    public function predict(string $reviewText): array
    {
        $url = config('services.ai.url').'/predict';
        $timeout = (int) config('services.ai.timeout', 30);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($url, ['review' => $reviewText]);
        } catch (ConnectionException $e) {
            throw new \RuntimeException($this->connectionHelpMessage(), 0, $e);
        }

        if ($response->failed()) {
            Log::error('AI service error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException(
                $response->json('error') ?? 'AI sentiment service unavailable. Start Python: python app.py'
            );
        }

        return $response->json();
    }

    /**
     * @param  list<string>  $reviewTexts
     */
    public function predictBatch(array $reviewTexts): array
    {
        $url = config('services.ai.url').'/predict/batch';
        $timeout = (int) config('services.ai.timeout', 120);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($url, ['reviews' => array_values($reviewTexts)]);
        } catch (ConnectionException $e) {
            throw new \RuntimeException($this->connectionHelpMessage(), 0, $e);
        }

        if ($response->failed()) {
            Log::error('AI batch error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException(
                $response->json('error') ?? 'AI batch service unavailable.'
            );
        }

        return $response->json();
    }

    protected function connectionHelpMessage(): string
    {
        $base = rtrim((string) config('services.ai.url'), '/');

        return "AI sentiment service is not reachable at {$base}. "
            .'Start it: from capstonefront run `npm run sentiment`, '
            .'or in C:\\Users\\HP\\ai\\ai-service run `venv\\Scripts\\python.exe app.py` (port 5001).';
    }
}
