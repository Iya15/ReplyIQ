<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicIdGenerator
{
    private const PREFIX = 'cb_';

    private const RANDOM_LENGTH = 13;

    /**
     * Generates a unique public_id, retrying on the rare collision.
     * Typical case: one DB read + zero retries.
     */
    public static function generate(): string
    {
        do {
            $id = self::PREFIX.Str::random(self::RANDOM_LENGTH);
        } while (! self::check($id));

        return $id;
    }

    // Returns true when the given id is available (not yet in use).
    public static function check(string $id): bool
    {
        return ! DB::table('chatbots')->where('public_id', $id)->exists();
    }
}
