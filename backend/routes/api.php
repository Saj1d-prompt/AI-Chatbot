<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChatController;

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'AI Chatbot API is running.',
    ]);
});

Route::post('/chat', [ChatController::class, 'store']);