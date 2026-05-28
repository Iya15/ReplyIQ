<?php

namespace App\Services\Public;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Psr\Clock\ClockInterface;

/**
 * Issues and verifies short-lived JWT session tokens for widget visitors.
 *
 * The token is scoped to a single (chatbot, conversation, visitor) triple and
 * expires after 24 h. It is signed with the application's APP_KEY via HMAC-SHA256
 * so no additional secret management is required.
 *
 * Token claims:
 *   iss  → 'replyiq.widget'
 *   iat  → issued-at timestamp
 *   exp  → expiry timestamp (iat + 24 h)
 *   cid  → chatbot public_id
 *   cnv  → conversation UUID
 *   vid  → visitor UUID
 *
 * Replacing the M3.1 HMAC scheme — see docs/architecture/decisions/0004-widget-auth.md.
 */
class WidgetSessionToken
{
    private const ISSUER    = 'replyiq.widget';
    private const TTL_HOURS = 24;

    private Configuration $jwt;

    public function __construct()
    {
        $this->jwt = $this->buildConfig();
    }

    public function issue(string $chatbotPublicId, string $conversationId, string $visitorId): string
    {
        $now = new DateTimeImmutable();

        return $this->jwt
            ->builder()
            ->issuedBy(self::ISSUER)
            ->issuedAt($now)
            ->expiresAt($now->modify('+' . self::TTL_HOURS . ' hours'))
            ->withClaim('cid', $chatbotPublicId)
            ->withClaim('cnv', $conversationId)
            ->withClaim('vid', $visitorId)
            ->getToken($this->jwt->signer(), $this->jwt->signingKey())
            ->toString();
    }

    /**
     * Verify the token and return its claims, or null if invalid / expired.
     *
     * @return array{chatbot_id: string, conversation_id: string, visitor_id: string}|null
     */
    public function verify(string $tokenString): ?array
    {
        try {
            $token = $this->jwt->parser()->parse($tokenString);
            assert($token instanceof Plain);

            $this->jwt->validator()->assert(
                $token,
                new IssuedBy(self::ISSUER),
                new LooseValidAt(new class implements ClockInterface {
                    public function now(): \DateTimeImmutable { return new \DateTimeImmutable(); }
                }),
            );

            return [
                'chatbot_id'      => (string) $token->claims()->get('cid'),
                'conversation_id' => (string) $token->claims()->get('cnv'),
                'visitor_id'      => (string) $token->claims()->get('vid'),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildConfig(): Configuration
    {
        $appKey = (string) config('app.key');

        // APP_KEY in Laravel is stored as "base64:{encoded}" in .env.
        $key = str_starts_with($appKey, 'base64:')
            ? InMemory::base64Encoded(substr($appKey, 7))
            : InMemory::plainText($appKey);

        return Configuration::forSymmetricSigner(new Sha256(), $key);
    }
}
