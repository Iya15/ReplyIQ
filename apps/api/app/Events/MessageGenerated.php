<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Message $message,
    ) {}
}
