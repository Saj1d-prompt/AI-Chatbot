<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatRequest;
use App\Services\AI\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService
    ) {
    }

    public function store(ChatRequest $request): JsonResponse
    {
        try {
            $reply = $this->chatService->sendMessage(
                $request->validated('message')
            );

            return response()->json([
                'success' => true,
                'reply' => $reply,
            ]);
        } catch (Throwable $exception) {
            Log::error('AI chat request failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The AI service is temporarily unavailable. Please try again.',
            ], 502);
        }
    }
}