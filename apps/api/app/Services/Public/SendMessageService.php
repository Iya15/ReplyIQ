<?php

namespace App\Services\Public;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Jobs\GenerateAiReplyJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Support\Facades\Redis;

class SendMessageService
{
    public function __construct(private readonly AnalyticsRecorder $analytics) {}

    /**
     * Persist the user's message, create a pending assistant placeholder,
     * and dispatch the AI reply job.
     *
     * @return array{user: Message, assistant: Message}
     */
    public function execute(Conversation $conversation, string $content): array
    {
        // Persist the user message.
        $userMessage = Message::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'role'            => MessageRole::User,
            'content'         => $content,
            'status'          => MessageStatus::Complete,
        ]);

        // Create the assistant placeholder — status = 'pending' until the job finishes.
        $assistantMessage = Message::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'role'            => MessageRole::Assistant,
            'content'         => '',
            'status'          => MessageStatus::Pending,
        ]);

        // Dispatch the AI reply job to the 'replies' queue.
        GenerateAiReplyJob::dispatch($conversation, $assistantMessage);

        // Increment the monthly message counter for plan-limit checks.
        // Fire-and-forget — Redis errors must not fail the message send.
        try {
            $key = 'usage:messages:' . $conversation->organization_id . ':' . now()->format('Y-m');
            Redis::incr($key);
            // TTL: end of current month + 7-day grace period.
            Redis::expireat($key, (int) now()->endOfMonth()->addDays(7)->timestamp);
        } catch (\Throwable) {
            // Intentionally swallowed — analytics/billing counters are non-critical.
        }

        $this->analytics->record(
            eventType:      'message_sent',
            organizationId: (string) $conversation->organization_id,
            chatbotId:      (string) $conversation->chatbot_id,
            conversationId: (string) $conversation->id,
            context: [
                'content_preview' => mb_substr($content, 0, 200),
            ],
        );

        return [
            'user'      => $userMessage,
            'assistant' => $assistantMessage,
        ];
    }
}
