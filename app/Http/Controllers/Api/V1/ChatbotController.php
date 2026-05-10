<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Services\ChatbotService;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChatbotController extends Controller
{
    public function __construct(
        protected ChatbotService $chatbotService
    ) {}

    public function message(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:1000'],
            'conversation_id' => ['nullable', 'uuid', Rule::exists('chatbot_conversations', 'id')],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $user = $request->user();

        if ($request->filled('conversation_id')) {
            $conversation = ChatbotConversation::query()
                ->where('id', $request->string('conversation_id'))
                ->where('user_id', $user->id)
                ->firstOrFail();
        } else {
            $conversation = ChatbotConversation::create([
                'user_id' => $user->id,
                'status' => 'active',
            ]);
        }

        ChatbotMessage::create([
            'conversation_id' => $conversation->id,
            'sender' => 'user',
            'message' => $request->string('message')->toString(),
        ]);

        $lat = $request->has('latitude') ? (float) $request->input('latitude') : null;
        $lng = $request->has('longitude') ? (float) $request->input('longitude') : null;

        $history = $this->buildChatHistory($conversation->id);

        $result = $this->chatbotService->processMessage(
            $request->string('message')->toString(),
            $user->id,
            $lat,
            $lng,
            $history,
            $conversation->id
        );

        $botMessage = ChatbotMessage::create([
            'conversation_id' => $conversation->id,
            'sender' => 'bot',
            'message' => $result['response'],
            'recommendations' => $result['recommendations'],
            'google_places' => $result['google_places'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => $result['response'],
                'recommendations' => $result['recommendations'],
                'google_places' => $result['google_places'] ?? [],
                'intent' => $result['intent'],
                'conversation_id' => $conversation->id,
                'message_id' => $botMessage->id,
            ],
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $conversations = ChatbotConversation::query()
            ->where('user_id', $request->user()->id)
            ->with('messages')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $conversations]);
    }

    public function showConversation(Request $request, string $id): JsonResponse
    {
        $conversation = ChatbotConversation::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with('messages')
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $conversation]);
    }

    /**
     * Prior user/assistant turns for the current conversation (excludes the message just saved).
     *
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildChatHistory(string $conversationId): array
    {
        $rows = ChatbotMessage::query()
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['sender', 'message']);

        if ($rows->count() <= 1) {
            return [];
        }

        $rows->pop();

        $out = [];
        foreach ($rows as $row) {
            $text = trim(Str::limit((string) $row->message, 2000));
            if ($text === '') {
                continue;
            }
            $out[] = [
                'role' => $row->sender === 'user' ? 'user' : 'assistant',
                'content' => $text,
            ];
        }

        return $out;
    }
}
