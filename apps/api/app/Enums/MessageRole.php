<?php

namespace App\Enums;

enum MessageRole: string
{
    case User      = 'user';
    case Assistant = 'assistant';
    case Agent     = 'agent';
    case System    = 'system';
}
