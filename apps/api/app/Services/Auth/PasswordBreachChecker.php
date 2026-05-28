<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Checks a plain-text password against the HaveIBeenPwned k-anonymity API.
 *
 * Only the first 5 characters of the SHA-1 hash are sent to the API; the
 * full hash never leaves the server. Fail-open: a network error does NOT
 * block registration.
 *
 * @see https://haveibeenpwned.com/API/v3#SearchingPwnedPasswordsByRange
 */
class PasswordBreachChecker
{
    private const HIBP_URL = 'https://api.pwnedpasswords.com/range/';

    public function isBreached(string $password): bool
    {
        $hash = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);

        try {
            $response = Http::timeout(3)
                ->withHeaders(['Add-Padding' => 'true'])
                ->get(self::HIBP_URL.$prefix);

            if (! $response->ok()) {
                return false; // fail-open on API error
            }

            foreach (explode("\n", trim($response->body())) as $line) {
                $parts = explode(':', trim($line), 2);
                if (count($parts) !== 2) {
                    continue;
                }
                [$lineSuffix, $count] = $parts;
                if (strtoupper($lineSuffix) === $suffix && (int) $count > 0) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('PasswordBreachChecker: HIBP check failed, allowing password', [
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
