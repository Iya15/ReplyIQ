<?php

namespace App\Enums;

enum DocumentSourceType: string
{
    case Pdf = 'pdf';
    case Docx = 'docx';
    case Txt = 'txt';
    case Url = 'url';
    case Manual = 'manual';
    case Faq = 'faq';
}
