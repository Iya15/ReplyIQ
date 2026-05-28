<?php

namespace App\Jobs;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Events\MessageCompleted;
use App\Events\MessageTokenStreamed;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Ai\RagPipeline;
use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateAiReplyJob implements ShouldQueue
{
    use Queueable;

    /** Separate queue from ingestion so long crawls don't delay chat replies. */
    public ?string $queue = 'replies';

    /** Reply must arrive within 60 s or it's meaningless to the visitor. */
    public int $timeout = 60;

    /** Retry twice on transient failures (API rate-limits, timeouts). */
    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [5, 15];

    public function __construct(
        public readonly Conversation $conversation,
        public readonly Message      $assistantMessage,
    ) {}

    public function handle(RagPipeline $pipeline, AnalyticsRecorder $analytics): void
    {
        // ── 1. Resolve tenant context ──────────────────────────────────────────
        // BelongsToTenant uses 'currentOrganization' container binding.
        // Jobs don't go through middleware, so we bind it manually from the model.
        $conversation = $this->conversation->loadMissing(['chatbot.settings', 'chatbot.organization']);
        $chatbot      = $conversation->chatbot;
        app()->instance('currentOrganization', $chatbot->organization);

        // ── 2. Build query and history ─────────────────────────────────────────
        // Load all complete messages for this conversation ordered by creation.
        $allMessages = Message::where('conversation_id', $conversation->id)
            ->whereIn('status', [MessageStatus::Complete->value])
            ->orderBy('created_at')
            ->get();

        // The last complete user message is the current query.
        $userMessage = $allMessages
            ->where('role', MessageRole::User)
            ->last();

        if (! $userMessage) {
            Log::warning('GenerateAiReplyJob: no user message found', [
                'conversation_id' => $conversation->id,
            ]);
            $this->markFailed("I'm sorry, something went wrong. Please try again.");
            $analytics->record(
                eventType:      'unanswered',
                organizationId: (string) $conversation->organization_id,
                chatbotId:      (string) $chatbot->id,
                conversationId: (string) $conversation->id,
                context:        ['reason' => 'no_user_message'],
            );
            return;
        }

        // All complete messages before the current user message form the history.
        /** @var array<int, array{role: string, content: string}> $history */
        $history = [];
        foreach ($allMessages as $historyMessage) {
            if ($historyMessage->id !== $userMessage->id) {
                $history[] = [
                    'role'    => $historyMessage->role->value,
                    'content' => $historyMessage->content,
                ];
            }
        }

        // ── 3. Run RAG pipeline with token streaming ───────────────────────────
        $messageId      = (string) $this->assistantMessage->id;
        $conversationId = (string) $conversation->id;

        try {
            $reply = $pipeline->execute(
                chatbot: $chatbot,
                query:   $userMessage->content,
                history: $history,
                onToken: function (string $token) use ($conversationId, $messageId): void {
                    broadcast(new MessageTokenStreamed(
                        conversationId: $conversationId,
                        messageId:      $messageId,
                        token:          $token,
                    ));
                },
            );
        } catch (\Throwable $e) {
            Log::error('GenerateAiReplyJob: pipeline exception', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);
            $this->markFailed(
                $chatbot->settings?->fallback_message
                ?? "I'm sorry, I'm unable to respond right now. Please try again."
            );
            throw $e; // Allow queue retry.
        }

        // ── 4. Persist the completed assistant message ─────────────────────────
        $this->assistantMessage->update([
            'content'     => $reply->content,
            'status'      => MessageStatus::Complete,
            'sources'     => $reply->sources,
            'confidence'  => $reply->confidence,
            'tokens_used' => $reply->tokens_used,
            'latency_ms'  => $reply->latency_ms,
        ]);

        $analytics->record(
            eventType:      'message_replied',
            organizationId: (string) $conversation->organization_id,
            chatbotId:      (string) $chatbot->id,
            conversationId: (string) $conversation->id,
            context: [
                'confidence'  => $reply->confidence,
                'tokens_used' => $reply->tokens_used,
                'latency_ms'  => $reply->latency_ms,
            ],
        );

        // ── 5. Broadcast completion so the client can finalize the message ─────
        broadcast(new MessageCompleted($this->assistantMessage->fresh()));
    }

    /**
     * Mark the assistant message as failed with a user-friendly message.
     * Does NOT throw — call only when we want to swallow the error.
     */
    private function markFailed(string $content): void
    {
        $this->assistantMessage->update([
            'content' => $content,
            'status'  => MessageStatus::Failed,
        ]);
    }

    /**
     * Laravel calls this when all retries are exhausted.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('GenerateAiReplyJob exhausted retries', [
            'conversation_id'    => $this->conversation->id,
            'assistant_msg_id'   => $this->assistantMessage->id,
            'error'              => $e->getMessage(),
        ]);

        // Ensure the placeholder doesn't stay 'pending' indefinitely.
        $fresh = $this->assistantMessage->fresh();

        /** @var Message $fresh */
        if ($fresh->status === MessageStatus::Pending) {
            $this->markFailed("I'm sorry, I'm unable to respond right now. Please try again.");
        }

        app(AnalyticsRecorder::class)->record(
            eventType:      'unanswered',
            organizationId: (string) $this->conversation->organization_id,
            chatbotId:      (string) $this->conversation->chatbot_id,
            conversationId: (string) $this->conversation->id,
            context:        ['reason' => 'retries_exhausted', 'error' => $e->getMessage()],
        );
    }
}
