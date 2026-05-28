<?php

namespace App\Services\Crawling;

use DOMDocument;
use DOMNode;
use DOMXPath;

class HtmlContentExtractor
{
    private const NOISE_XPATH = [
        '//script',
        '//style',
        '//nav',
        '//header',
        '//footer',
        '//aside',
        '//*[@role="navigation"]',
        '//*[@role="banner"]',
        '//*[@role="contentinfo"]',
        '//*[contains(@class,"cookie")]',
        '//*[contains(@class,"banner")]',
        '//*[contains(@class,"sidebar")]',
        '//*[contains(@id,"cookie")]',
        '//*[contains(@id,"sidebar")]',
    ];

    private const CONTENT_XPATH = ['//main', '//article', '//body'];

    /**
     * Extract the main readable text from an HTML string.
     *
     * Strategy:
     *  1. Remove noise elements (script, style, nav, ads, etc.).
     *  2. Prefer <main> → <article> → <body> as the content root.
     *  3. Return the text content with whitespace normalised.
     */
    public function extract(string $html): string
    {
        $dom = new DOMDocument;

        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new DOMXPath($dom);

        // Remove noise nodes in reverse order to avoid live-NodeList issues.
        foreach (self::NOISE_XPATH as $selector) {
            $nodes = $xpath->query($selector);

            if ($nodes === false) {
                continue;
            }

            $toRemove = [];

            foreach ($nodes as $node) {
                $toRemove[] = $node;
            }

            foreach ($toRemove as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Find the best content root.
        $contentNode = null;

        foreach (self::CONTENT_XPATH as $selector) {
            $nodes = $xpath->query($selector);

            if ($nodes !== false && $nodes->length > 0) {
                $contentNode = $nodes->item(0);
                break;
            }
        }

        $raw = $contentNode instanceof DOMNode
            ? $contentNode->textContent
            : $dom->textContent;

        return $this->normaliseWhitespace($raw);
    }

    /**
     * Extract the page title from an HTML string.
     * Falls back to the first <h1> text if <title> is missing or empty.
     */
    public function extractTitle(string $html): string
    {
        $dom = new DOMDocument;

        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new DOMXPath($dom);

        foreach (['//title', '//h1'] as $selector) {
            $nodes = $xpath->query($selector);

            if ($nodes !== false && $nodes->length > 0) {
                $text = trim($nodes->item(0)?->textContent ?? '');

                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    private function normaliseWhitespace(string $text): string
    {
        // Collapse horizontal whitespace within lines.
        $text = (string) preg_replace('/[^\S\n]+/', ' ', $text);
        // Collapse 3+ consecutive blank lines into two.
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
