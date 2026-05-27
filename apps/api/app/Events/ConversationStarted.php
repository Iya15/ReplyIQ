<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
    ) {}
}
