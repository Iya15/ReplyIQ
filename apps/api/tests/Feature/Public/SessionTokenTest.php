<?php

use App\Services\Public\WidgetSessionToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

uses(RefreshDatabase::class);

// ── Issue ─────────────────────────────────────────────────────────────────────

it('issues a JWT string with three dot-separated segments', function () {
    $svc   = app(WidgetSessionToken::class);
    $token = $svc->issue('pub-id', (string) Str::uuid(), (string) Str::uuid());

    expect($token)->toBeString()
        ->and(explode('.', $token))->toHaveCount(3);
});

// ── Verify — valid token ──────────────────────────────────────────────────────

it('verify returns correct claims from a freshly issued token', function () {
    $svc            = app(WidgetSessionToken::class);
    $chatbotId      = 'pub-' . Str::random(8);
    $conversationId = (string) Str::uuid();
    $visitorId      = (string) Str::uuid();

    $claims = $svc->verify($svc->issue($chatbotId, $conversationId, $visitorId));

    expect($claims)->toBeArray()
        ->and($claims['chatbot_id'])->toBe($chatbotId)
        ->and($claims['conversation_id'])->toBe($conversationId)
        ->and($claims['visitor_id'])->toBe($visitorId);
});

// ── Verify — invalid / rejected tokens ───────────────────────────────────────

it('verify returns null for a malformed token string', function () {
    $svc = app(WidgetSessionToken::class);

    expect($svc->verify('not.a.jwt'))->toBeNull()
        ->and($svc->verify(''))->toBeNull()
        ->and($svc->verify('random-garbage'))->toBeNull();
});

it('verify returns null for a token signed with the wrong key', function () {
    $wrongKey = InMemory::plainText(str_repeat('x', 32));
    $config   = Configuration::forSymmetricSigner(new Sha256(), $wrongKey);

    $forgedToken = $config->builder()
        ->issuedBy('replyiq.widget')
        ->issuedAt(new DateTimeImmutable())
        ->expiresAt((new DateTimeImmutable())->modify('+24 hours'))
        ->withClaim('cid', 'pub-id')
        ->withClaim('cnv', (string) Str::uuid())
        ->withClaim('vid', (string) Str::uuid())
        ->getToken($config->signer(), $config->signingKey())
        ->toString();

    expect(app(WidgetSessionToken::class)->verify($forgedToken))->toBeNull();
});

it('verify returns null for a token with the wrong issuer', function () {
    $appKey = (string) config('app.key');
    $key    = str_starts_with($appKey, 'base64:')
        ? InMemory::base64Encoded(substr($appKey, 7))
        : InMemory::plainText($appKey);
    $config = Configuration::forSymmetricSigner(new Sha256(), $key);

    $wrongIssuerToken = $config->builder()
        ->issuedBy('evil.issuer')
        ->issuedAt(new DateTimeImmutable())
        ->expiresAt((new DateTimeImmutable())->modify('+24 hours'))
        ->withClaim('cid', 'pub-id')
        ->withClaim('cnv', (string) Str::uuid())
        ->withClaim('vid', (string) Str::uuid())
        ->getToken($config->signer(), $config->signingKey())
        ->toString();

    expect(app(WidgetSessionToken::class)->verify($wrongIssuerToken))->toBeNull();
});

it('verify returns null for an expired token', function () {
    $appKey = (string) config('app.key');
    $key    = str_starts_with($appKey, 'base64:')
        ? InMemory::base64Encoded(substr($appKey, 7))
        : InMemory::plainText($appKey);
    $config = Configuration::forSymmetricSigner(new Sha256(), $key);

    $expiredToken = $config->builder()
        ->issuedBy('replyiq.widget')
        ->issuedAt(new DateTimeImmutable('-48 hours'))
        ->expiresAt(new DateTimeImmutable('-1 hour'))
        ->withClaim('cid', 'pub-id')
        ->withClaim('cnv', (string) Str::uuid())
        ->withClaim('vid', (string) Str::uuid())
        ->getToken($config->signer(), $config->signingKey())
        ->toString();

    expect(app(WidgetSessionToken::class)->verify($expiredToken))->toBeNull();
});
