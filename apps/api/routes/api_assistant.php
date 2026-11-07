<?php

// AI shopping assistant routes (prefix api/v1), behind the "ai_assistant" feature switch. See docs/API.md "AI assistant".

use App\Http\Controllers\Api\Assistant\AssistantController;
use Illuminate\Support\Facades\Route;

Route::middleware('feature:ai_assistant')->prefix('assistant')->group(function () {
    Route::get('config', [AssistantController::class, 'config'])->middleware('throttle:60,1');
    Route::post('chat', [AssistantController::class, 'chat'])->middleware('throttle:20,1');
    Route::get('conversations/{id}', [AssistantController::class, 'show'])->whereUuid('id')->middleware('throttle:60,1');
    Route::delete('conversations/{id}', [AssistantController::class, 'destroy'])->whereUuid('id')->middleware('throttle:30,1');
    Route::post('feedback', [AssistantController::class, 'feedback'])->middleware('throttle:60,1');
});
