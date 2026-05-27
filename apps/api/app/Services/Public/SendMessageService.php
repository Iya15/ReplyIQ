<?php

namespace App\Services\Public;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Jobs\GenerateAiReplyJob;
use App\Models\Conversation;
use App\Models\Message;

class SendMessageService
{
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

        return [
            'user'      => $userMessage,
            'assistant' => $assistantMessage,
        ];
    }
}
