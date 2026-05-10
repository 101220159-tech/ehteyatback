<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlamaService
{
    protected string $llamaUrl;

    protected string $model;

    public function __construct()
    {
        $this->llamaUrl = rtrim((string) env('LLAMA_API_URL', 'http://localhost:11434'), '/');
        $this->model = (string) env('LLAMA_MODEL', 'llama3.2:3b');
    }

    public function isRunning(): bool
    {
        try {
            $response = Http::timeout(2)->get($this->llamaUrl.'/api/tags');

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $priorMessages
     *                                                         user/assistant turns before this request (no system).
     */
    public function generateResponse(string $prompt, array $context = [], array $priorMessages = []): ?string
    {
        $systemPrompt = $this->buildSystemPrompt();
        $userContent = $this->buildUserPrompt($prompt, $context);
        $priorMessages = $this->trimChatHistory($priorMessages);

        $protocol = strtolower((string) env('LLAMA_OLLAMA_PROTOCOL', 'chat'));

        if ($protocol === 'anthropic') {
            $anthropic = $this->generateViaOllamaAnthropicApi($systemPrompt, $userContent, $priorMessages);
            if ($anthropic !== null) {
                return $anthropic;
            }
        }

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($priorMessages as $row) {
            $role = $row['role'] ?? '';
            $content = trim((string) ($row['content'] ?? ''));
            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }

        $messages[] = ['role' => 'user', 'content' => $userContent];

        try {
            $response = Http::timeout(120)->post($this->llamaUrl.'/api/chat', [
                'model' => $this->model,
                'messages' => $messages,
                'stream' => false,
                'options' => [
                    'temperature' => (float) env('LLAMA_TEMPERATURE', 0.65),
                    'num_predict' => (int) env('LLAMA_MAX_TOKENS', 500),
                    'top_p' => (float) env('LLAMA_TOP_P', 0.9),
                ],
            ]);

            if ($response->successful()) {
                $text = $response->json('message.content');
                if (is_string($text) && $text !== '') {
                    return $text;
                }
            }
        } catch (\Throwable $e) {
            Log::error('Llama API error: '.$e->getMessage());
        }

        return $this->generateResponseLegacy($userContent, $systemPrompt);
    }

    /**
     * Ollama’s Anthropic-compatible Messages API (POST /v1/messages).
     * Lets you point “Claude-style” clients at Ollama; the model is still a local Ollama model (not cloud Claude).
     *
     * @param  array<int, array{role: string, content: string}>  $priorMessages
     */
    protected function generateViaOllamaAnthropicApi(string $systemPrompt, string $userContent, array $priorMessages): ?string
    {
        $messages = [];
        foreach ($priorMessages as $row) {
            $role = $row['role'] ?? '';
            $content = trim((string) ($row['content'] ?? ''));
            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }
        $messages[] = ['role' => 'user', 'content' => $userContent];

        $payload = [
            'model' => $this->model,
            'max_tokens' => (int) env('LLAMA_MAX_TOKENS', 500),
            'system' => $systemPrompt,
            'messages' => $messages,
        ];
        $temp = (float) env('LLAMA_TEMPERATURE', 0.65);
        if ($temp > 0) {
            $payload['temperature'] = $temp;
        }
        $topP = (float) env('LLAMA_TOP_P', 0.9);
        if ($topP > 0 && $topP <= 1) {
            $payload['top_p'] = $topP;
        }

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'anthropic-version' => '2023-06-01',
                ])
                ->post($this->llamaUrl.'/v1/messages', $payload);

            if (! $response->successful()) {
                Log::warning('Ollama Anthropic API HTTP '.$response->status().': '.$response->body());

                return null;
            }

            $text = $this->extractAnthropicStyleText($response->json());
            if ($text !== '') {
                return $text;
            }
        } catch (\Throwable $e) {
            Log::error('Ollama Anthropic API error: '.$e->getMessage());
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    protected function extractAnthropicStyleText(?array $json): string
    {
        if ($json === null) {
            return '';
        }

        $content = $json['content'] ?? null;
        if (! is_array($content)) {
            return '';
        }

        $parts = [];
        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'text' && isset($block['text']) && is_string($block['text'])) {
                $parts[] = $block['text'];
            }
        }

        return trim(implode('', $parts));
    }

    /**
     * Fallback for older Ollama builds without a working /api/chat route.
     */
    protected function generateResponseLegacy(string $userPrompt, string $systemPrompt): ?string
    {
        try {
            $response = Http::timeout(120)->post($this->llamaUrl.'/api/generate', [
                'model' => $this->model,
                'prompt' => $userPrompt,
                'system' => $systemPrompt,
                'stream' => false,
                'options' => [
                    'temperature' => (float) env('LLAMA_TEMPERATURE', 0.65),
                    'num_predict' => (int) env('LLAMA_MAX_TOKENS', 500),
                    'top_p' => (float) env('LLAMA_TOP_P', 0.9),
                ],
            ]);

            if ($response->successful()) {
                return $response->json('response');
            }
        } catch (\Throwable $e) {
            Log::error('Llama legacy API error: '.$e->getMessage());
        }

        return null;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $priorMessages
     * @return array<int, array{role: string, content: string}>
     */
    protected function trimChatHistory(array $priorMessages): array
    {
        $max = max(4, min(24, (int) env('LLAMA_CHAT_HISTORY_MAX', 12)));
        if (count($priorMessages) <= $max) {
            return $priorMessages;
        }

        return array_slice($priorMessages, -$max);
    }

    protected function buildSystemPrompt(): string
    {
        return <<<'TXT'
You are RECO, the NexVex assistant for a home-services marketplace in Lebanon.
Your role is to help customers find and book service providers on NexVex.

RULES:
1. Be friendly and professional. Use the conversation so far—refer back when the customer clarifies or follows up.
2. Keep replies concise (max 4 short sentences) unless they ask for detail.
3. Only recommend NexVex bookable providers that appear in "AVAILABLE PROVIDERS". Never invent names or ratings.
4. If "GOOGLE MAPS REFERENCES" appears, those are public listings for ideas only—they are NOT bookable on NexVex unless the same business is also in AVAILABLE PROVIDERS. Say that clearly in one short phrase when you mention them.
5. If both lists are empty, say you could not find a match and suggest they try another service or area.
6. If something is unclear (service type, city, urgency, budget), ask one focused question.
7. Prefer higher-rated NexVex providers when several are listed; mention location or distance when it helps compare.

SERVICES: plumbing, electrical, cleaning, painting, AC repair,
          carpentry, gardening, moving.

LOCATIONS: Beirut, Tripoli, Jounieh, Byblos, Zahle, Saida, Tyre.

Respond in English unless the customer writes in Arabic—then reply in Arabic.
TXT;
    }

    protected function buildUserPrompt(string $message, array $context): string
    {
        $prompt = "Customer message: \"{$message}\"\n\n";

        if (! empty($context['intent_summary'])) {
            $prompt .= "Detected intent this turn: {$context['intent_summary']}\n\n";
        }

        if (! empty($context['recommendations'])) {
            $prompt .= "AVAILABLE PROVIDERS (book on NexVex):\n";
            foreach ($context['recommendations'] as $provider) {
                $prompt .= '- '.$provider['name'].': ';
                $prompt .= 'Rating '.($provider['platform_rating'] ?? 'n/a').'⭐ ';
                if (isset($provider['google_rating'])) {
                    $prompt .= '(Google '.$provider['google_rating'].'⭐) ';
                }
                if (isset($provider['distance_km'])) {
                    $prompt .= '~'.$provider['distance_km'].' km ';
                }
                $prompt .= '- '.($provider['location'] ?? '')."\n";
            }
        }

        if (! empty($context['google_places'])) {
            $prompt .= "\nGOOGLE MAPS REFERENCES (not NexVex bookings; for discovery only):\n";
            foreach ($context['google_places'] as $g) {
                $prompt .= '- '.($g['name'] ?? '').': ';
                if (isset($g['google_rating'])) {
                    $prompt .= 'Google '.($g['google_rating'] ?? 'n/a').'⭐ ';
                }
                if (! empty($g['google_review_count'])) {
                    $prompt .= '('.$g['google_review_count'].' Google reviews) ';
                }
                $prompt .= '- '.($g['location'] ?? '')."\n";
            }
        }

        $prompt .= "\nRespond naturally to help the customer find the best provider.";

        return $prompt;
    }
}
