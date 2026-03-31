<?php

namespace App\Services;

use App\Models\User;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FirebaseService
{
    protected ?string $projectId = null;

    public function saveToken(User $user, ?string $token): void
    {
        $user->update(['fcm_token' => $token]);
    }

    /**
     * @param  array<string, string>  $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if (! $user->fcm_token) {
            return;
        }

        $this->sendToTokens([$user->fcm_token], $title, $body, $data);
    }

    /**
     * @param  iterable<User>  $users
     * @param  array<string, string>  $data
     */
    public function sendToMultipleUsers(iterable $users, string $title, string $body, array $data = []): void
    {
        $tokens = Collection::make($users)
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tokens === []) {
            return;
        }

        $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * @param  list<string>  $tokens
     * @param  array<string, string>  $data
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        $path = config('services.firebase.credentials');
        if (! $path || ! is_readable($path)) {
            Log::debug('Firebase push skipped: credentials file not configured or unreadable.');

            return;
        }

        $projectId = $this->resolveProjectId($path);
        if (! $projectId) {
            Log::warning('Firebase push skipped: could not resolve project id.');

            return;
        }

        try {
            $accessToken = $this->accessToken($path);
        } catch (Throwable $e) {
            Log::error('Firebase auth failed.', ['exception' => $e->getMessage()]);

            return;
        }

        if ($accessToken === '') {
            Log::warning('Firebase push skipped: empty access token.');

            return;
        }

        $url = 'https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send';

        $stringData = [];
        foreach ($data as $key => $value) {
            $stringData[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        foreach ($tokens as $token) {
            $message = [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ];
            if ($stringData !== []) {
                $message['data'] = $stringData;
            }

            $payload = ['message' => $message];

            try {
                $response = Http::withToken($accessToken)
                    ->timeout(15)
                    ->post($url, $payload);

                if (! $response->successful()) {
                    Log::warning('FCM send failed.', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            } catch (Throwable $e) {
                Log::error('FCM HTTP error.', ['exception' => $e->getMessage()]);
            }
        }
    }

    protected function accessToken(string $credentialsPath): string
    {
        $json = json_decode((string) file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);
        $creds = new ServiceAccountCredentials(
            'https://www.googleapis.com/auth/firebase.messaging',
            $json
        );
        $token = $creds->fetchAuthToken();

        return $token['access_token'] ?? '';
    }

    protected function resolveProjectId(string $credentialsPath): ?string
    {
        if ($this->projectId) {
            return $this->projectId;
        }

        $fromConfig = config('services.firebase.project_id');
        if (is_string($fromConfig) && $fromConfig !== '') {
            return $this->projectId = $fromConfig;
        }

        $json = json_decode((string) file_get_contents($credentialsPath), true);
        $id = $json['project_id'] ?? null;

        return $this->projectId = is_string($id) ? $id : null;
    }
}
