<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PostgresArray implements CastsAttributes
{
    /**
     * Convert the PostgreSQL TEXT[] wire format "{item1,item2}" to a PHP array.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [];
        }

        // PostgreSQL returns text arrays as: {item1,"item with comma",item3}
        if (str_starts_with($value, '{') && str_ends_with($value, '}')) {
            $inner = substr($value, 1, -1);
            if ($inner === '') {
                return [];
            }

            // str_getcsv handles quoted items with embedded commas/quotes.
            return str_getcsv($inner, ',', '"', '\\');
        }

        // Fallback for values already serialised as JSON by Eloquent.
        return json_decode($value, true) ?? [];
    }

    /**
     * Convert a PHP array back to the PostgreSQL TEXT[] literal format.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (empty($value)) {
            return '{}';
        }

        $items = array_map(function (string $item): string {
            $needsQuoting = str_contains($item, ',')
                || str_contains($item, '"')
                || str_contains($item, ' ')
                || str_contains($item, '\\');

            return $needsQuoting
                ? '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $item).'"'
                : $item;
        }, array_values($value));

        return '{'.implode(',', $items).'}';
    }
}
