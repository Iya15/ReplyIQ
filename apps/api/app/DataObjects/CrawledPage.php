<?php

namespace App\DataObjects;

final readonly class CrawledPage
{
    public function __construct(
        public string $url,
        public string $title,
        public string $content,
    ) {}
}
