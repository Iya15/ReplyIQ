<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Chatbots\ChatbotsController;
use App\Http\Controllers\Api\V1\Chatbots\ChatbotSettingsController;
use App\Http\Controllers\Api\V1\Documents\DocumentsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes here are loaded by bootstrap/app.php under the "api" middleware
| group and automatically prefixed with /api.
|
| Versioned business routes live under /api/v1/ and will be added as each
| milestone is implemented. Do NOT put business logic directly in this file.
|
*/

Route::get('/up', fn () => response()->json(['status' => 'ok']));

Route::prefix('v1')->group(function () {

    // ── Auth ─────────────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {

        // Public endpoints — rate-limited to 5/min per IP to prevent brute-force.
        Route::middleware('throttle:auth')->group(function () {
            Route::post('register', [AuthController::class, 'register']);
            Route::post('login', [AuthController::class, 'login']);
            Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        });

        Route::post('reset-password', [AuthController::class, 'resetPassword']);

        // Signed URL: id + hash emitted by VerifyEmail notification,
        // signature validated inside the controller via Request::hasValidSignature().
        Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->name('verification.verify');

        // Authenticated endpoints.
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    // ── Chatbots ──────────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::apiResource('chatbots', ChatbotsController::class);
        Route::get('chatbots/{chatbot}/embed-code', [ChatbotsController::class, 'embedCode']);
        Route::get('chatbots/{chatbot}/settings', [ChatbotSettingsController::class, 'show']);
        Route::patch('chatbots/{chatbot}/settings', [ChatbotSettingsController::class, 'update']);

        // ── Documents ─────────────────────────────────────────────────────────
        Route::get('chatbots/{chatbot}/documents', [DocumentsController::class, 'index']);
        Route::post('chatbots/{chatbot}/documents', [DocumentsController::class, 'storeFile']);
        Route::post('chatbots/{chatbot}/documents/text', [DocumentsController::class, 'storeText']);
        Route::post('chatbots/{chatbot}/documents/url', [DocumentsController::class, 'storeUrl']);
        Route::delete('documents/{document}', [DocumentsController::class, 'destroy']);
        Route::post('documents/{document}/reprocess', [DocumentsController::class, 'reprocess']);
    });
});
