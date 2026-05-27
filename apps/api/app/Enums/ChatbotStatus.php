<?php

namespace App\Enums;

enum ChatbotStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
}
