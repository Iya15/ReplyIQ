<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ApiKeys\ApiKeysController;
use App\Http\Controllers\Api\V1\Chatbots\AnalyticsController;
use App\Http\Controllers\Api\V1\Chatbots\ChatbotsController;
use App\Http\Controllers\Api\V1\Chatbots\ChatbotSettingsController;
use App\Http\Controllers\Api\V1\Invitations\AcceptController as InvitationAcceptController;
use App\Http\Controllers\Api\V1\Organization\InvitationsController as OrgInvitationsController;
use App\Http\Controllers\Api\V1\Organization\MembersController as OrgMembersController;
use App\Http\Controllers\Api\V1\Conversations\ConversationsController;
use App\Http\Controllers\Api\V1\Documents\DocumentsController;
use App\Http\Controllers\Api\V1\Public\BroadcastingAuthController as PublicBroadcastingAuthController;
use App\Http\Controllers\Api\V1\Public\ChatbotsController as PublicChatbotsController;
use App\Http\Controllers\Api\V1\Public\ConversationsController as PublicConversationsController;
use App\Http\Controllers\Api\V1\Public\EventsController as PublicEventsController;
use App\Http\Controllers\Api\V1\Public\MessagesController as PublicMessagesController;
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

    // ── Team management (invitation accept — no auth required) ────────────────
    Route::get('invitations/{token}',        [InvitationAcceptController::class, 'show']);
    Route::post('invitations/{token}/accept', [InvitationAcceptController::class, 'store']);

    // ── Chatbots ──────────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::apiResource('chatbots', ChatbotsController::class);
        Route::get('chatbots/{chatbot}/embed-code', [ChatbotsController::class, 'embedCode']);
        Route::get('chatbots/{chatbot}/settings', [ChatbotSettingsController::class, 'show']);
        Route::patch('chatbots/{chatbot}/settings', [ChatbotSettingsController::class, 'update']);

        // ── API Keys ──────────────────────────────────────────────────────────
        Route::apiResource('api-keys', ApiKeysController::class)->only(['index', 'store', 'destroy']);

        // ── Team ──────────────────────────────────────────────────────────────
        Route::prefix('organizations/current')->group(function () {
            Route::get('members',                        [OrgMembersController::class, 'index']);
            Route::patch('members/{user_id}',            [OrgMembersController::class, 'update']);
            Route::delete('members/{user_id}',           [OrgMembersController::class, 'destroy']);
            Route::get('invitations',                    [OrgInvitationsController::class, 'index']);
            Route::post('invitations',                   [OrgInvitationsController::class, 'store']);
            Route::delete('invitations/{invitation_id}', [OrgInvitationsController::class, 'destroy']);
        });

        // ── Analytics ─────────────────────────────────────────────────────────
        Route::get('chatbots/{chatbot}/analytics/overview',     [AnalyticsController::class, 'overview']);
        Route::get('chatbots/{chatbot}/analytics/conversations', [AnalyticsController::class, 'conversations']);
        Route::get('chatbots/{chatbot}/analytics/topics',       [AnalyticsController::class, 'topics']);
        Route::get('chatbots/{chatbot}/analytics/unanswered',   [AnalyticsController::class, 'unanswered']);

        // ── Conversations ──────────────────────────────────────────────────────
        Route::get('chatbots/{chatbot}/conversations', [ConversationsController::class, 'index']);
        Route::get('conversations/{conversation}', [ConversationsController::class, 'show']);
        Route::get('conversations/{conversation}/messages', [ConversationsController::class, 'messages']);
        Route::post('conversations/{conversation}/resolve', [ConversationsController::class, 'resolve']);

        // ── Documents ─────────────────────────────────────────────────────────
        Route::get('chatbots/{chatbot}/documents', [DocumentsController::class, 'index']);
        Route::post('chatbots/{chatbot}/documents', [DocumentsController::class, 'storeFile']);
        Route::post('chatbots/{chatbot}/documents/text', [DocumentsController::class, 'storeText']);
        Route::post('chatbots/{chatbot}/documents/url', [DocumentsController::class, 'storeUrl']);
        Route::delete('documents/{document}', [DocumentsController::class, 'destroy']);
        Route::post('documents/{document}/reprocess', [DocumentsController::class, 'reprocess']);
    });

    // ── External API (API-key authenticated) ─────────────────────────────────
    // Endpoints for programmatic access will be added here in a future milestone.
    Route::prefix('external')->middleware('api-key')->group(function () {
        // placeholder — no external endpoints yet
    });

    // ── Public (widget) API ───────────────────────────────────────────────────
    // 'throttle:widget' = 60 req/min per IP.
    // Auth modes — see WidgetAuth middleware and ADR-0004:
    //   widget:config  origin check only  (read-only + conversation start)
    //   widget:token   Bearer JWT          (all mutating/polling endpoints)
    //   widget         HMAC               (Reverb presence auth only)
    Route::prefix('public')->middleware('throttle:widget')->group(function () {

        // Read-only chatbot config — origin check only.
        Route::get(
            'chatbots/{public_id}/config',
            [PublicChatbotsController::class, 'config'],
        )->middleware('widget:config')->name('public.chatbots.config');

        // Start conversation — origin check only; returns session_token.
        Route::post(
            'conversations',
            [PublicConversationsController::class, 'store'],
        )->middleware('widget:config')->name('public.conversations.store');

        // Widget lifecycle events (widget_opened, widget_closed) — origin check only.
        Route::post(
            'events',
            [PublicEventsController::class, 'store'],
        )->middleware('widget:config')->name('public.events.store');

        // Session-token protected endpoints.
        Route::middleware('widget:token')->group(function () {
            Route::patch(
                'conversations/{id}',
                [PublicConversationsController::class, 'updateVisitor'],
            )->name('public.conversations.update');

            Route::post(
                'conversations/{id}/messages',
                [PublicConversationsController::class, 'sendMessage'],
            )->middleware('throttle:widget-send')->name('public.conversations.messages.store');

            Route::get(
                'conversations/{id}/messages',
                [PublicConversationsController::class, 'messages'],
            )->name('public.conversations.messages.index');

            Route::post(
                'messages/{id}/feedback',
                [PublicMessagesController::class, 'feedback'],
            )->name('public.messages.feedback');

            // Reverb presence channel auth for widget visitors (JWT replaces HMAC).
            Route::post(
                'broadcasting/auth',
                PublicBroadcastingAuthController::class,
            )->name('public.broadcasting.auth');
        });
    });
});
