<?php

namespace App\Services\Knowledge;

class Chunker
{
    // Token estimation: 1 word ≈ 1.3 tokens (GPT-4 tokenization average for English prose).
    // Accurate counting would require yethee/tiktoken-php; the ~30% error is acceptable
    // here because chunk boundaries are soft limits, not hard size guarantees.
    private const TOKEN_RATIO = 1.3;

    private const SEPARATORS = ["\n\n", "\n", '. ', ' '];

    /**
     * Split text into overlapping chunks of at most $maxTokens estimated tokens.
     *
     * @return string[]
     */
    public function chunk(string $text, int $maxTokens = 500, int $overlap = 50): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $pieces = $this->recursiveSplit($text, self::SEPARATORS, $maxTokens);

        return $this->mergeWithOverlap($pieces, $maxTokens, $overlap);
    }

    /**
     * Recursively split text using progressively finer separators until
     * each piece is within $maxTokens.
     *
     * @param  string[]  $separators
     * @return string[]
     */
    private function recursiveSplit(string $text, array $separators, int $maxTokens): array
    {
        if ($this->estimateTokens($text) <= $maxTokens) {
            return [$text];
        }

        if ($separators === []) {
            return $this->hardSplit($text, $maxTokens);
        }

        $sep = $separators[0];
        $remaining = array_slice($separators, 1);

        $parts = array_values(array_filter(
            array_map('trim', explode($sep, $text)),
            fn (string $p): bool => $p !== '',
        ));

        $result = [];

        foreach ($parts as $part) {
            if ($this->estimateTokens($part) > $maxTokens) {
                $result = array_merge($result, $this->recursiveSplit($part, $remaining, $maxTokens));
            } else {
                $result[] = $part;
            }
        }

        return $result;
    }

    /**
     * Greedily merge pieces into chunks, prepending overlap from the previous chunk.
     *
     * @param  string[]  $pieces
     * @return string[]
     */
    private function mergeWithOverlap(array $pieces, int $maxTokens, int $overlap): array
    {
        $chunks = [];
        $current = '';
        $currentTokens = 0;

        foreach ($pieces as $piece) {
            $pieceTokens = $this->estimateTokens($piece);

            if ($current === '') {
                $current = $piece;
                $currentTokens = $pieceTokens;

                continue;
            }

            if ($currentTokens + $pieceTokens > $maxTokens) {
                $chunks[] = $current;
                $tail = $this->extractTailTokens($current, $overlap);
                $current = $tail !== '' ? $tail."\n\n".$piece : $piece;
                $currentTokens = $this->estimateTokens($current);
            } else {
                $current .= "\n\n".$piece;
                $currentTokens += $pieceTokens;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = $current;
        }

        return array_values(array_filter(
            $chunks,
            fn (string $c): bool => trim($c) !== '',
        ));
    }

    /**
     * Extract approximately $tokenCount tokens from the end of $text,
     * always cutting on a word boundary.
     */
    private function extractTailTokens(string $text, int $tokenCount): string
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $wordCount = (int) ceil($tokenCount / self::TOKEN_RATIO);

        return implode(' ', array_slice($words, -$wordCount));
    }

    /**
     * Hard word-boundary split when no separator works.
     *
     * @return string[]
     */
    private function hardSplit(string $text, int $maxTokens): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $wordsPerChunk = max(1, (int) floor($maxTokens / self::TOKEN_RATIO));
        $chunks = [];

        foreach (array_chunk($words, $wordsPerChunk) as $group) {
            $chunks[] = implode(' ', $group);
        }

        return $chunks;
    }

    private function estimateTokens(string $text): int
    {
        return (int) ceil(str_word_count($text) * self::TOKEN_RATIO);
    }
}
