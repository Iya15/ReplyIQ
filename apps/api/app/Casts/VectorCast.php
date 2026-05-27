<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Converts between pgvector's wire format and a PHP float array.
 *
 * pgvector returns vectors as:  [0.1,0.2,0.30000001192]
 * (square brackets, comma-separated, no spaces, float4 precision)
 *
 * Note: pgvector stores float4 (32-bit). Values with more than ~7 significant
 * digits will lose precision on the round-trip. For embeddings this is fine —
 * the retrieval quality difference is negligible.
 */
class VectorCast implements CastsAttributes
{
    /**
     * @return float[]|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        // pgvector wire format: [x1,x2,...,xn]
        $inner = trim((string) $value, '[]');

        if ($inner === '') {
            return [];
        }

        return array_map('floatval', explode(',', $inner));
    }

    /**
     * @param  float[]|null  $value
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        // Format each element as a decimal string without unnecessary trailing zeros.
        // PHP's (string) cast on floats uses enough digits to round-trip through
        // float64, but pgvector will round to float4 on storage anyway.
        $formatted = array_map(
            fn (float|int $v): string => rtrim(rtrim(number_format((float) $v, 10, '.', ''), '0'), '.'),
            $value
        );

        return '['.implode(',', $formatted).']';
    }
}
