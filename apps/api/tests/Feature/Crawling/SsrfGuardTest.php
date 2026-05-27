<?php

use App\Exceptions\SsrfBlockedException;
use App\Services\Crawling\SsrfGuard;

// ── isPrivateIp ───────────────────────────────────────────────────────────────

it('blocks loopback IPv4', function () {
    expect(SsrfGuard::isPrivateIp('127.0.0.1'))->toBeTrue();
    expect(SsrfGuard::isPrivateIp('127.255.255.255'))->toBeTrue();
});

it('blocks RFC-1918 private ranges', function () {
    expect(SsrfGuard::isPrivateIp('10.0.0.1'))->toBeTrue();
    expect(SsrfGuard::isPrivateIp('10.255.255.255'))->toBeTrue();
    expect(SsrfGuard::isPrivateIp('172.16.0.1'))->toBeTrue();
    expect(SsrfGuard::isPrivateIp('172.31.255.255'))->toBeTrue();
    expect(SsrfGuard::isPrivateIp('192.168.0.1'))->toBeTrue();
    expect(SsrfGuard::isPrivateIp('192.168.255.255'))->toBeTrue();
});

it('blocks the AWS metadata endpoint 169.254.169.254', function () {
    expect(SsrfGuard::isPrivateIp('169.254.169.254'))->toBeTrue();
    expect(SsrfGuard::isPrivateIp('169.254.0.1'))->toBeTrue();
});

it('blocks shared address space 100.64.x.x', function () {
    expect(SsrfGuard::isPrivateIp('100.64.0.1'))->toBeTrue();
    expect(SsrfGuard::isPrivateIp('100.127.255.255'))->toBeTrue();
});

it('blocks IPv6 loopback', function () {
    expect(SsrfGuard::isPrivateIp('::1'))->toBeTrue();
});

it('blocks IPv6 unique-local (fc00::/7)', function () {
    expect(SsrfGuard::isPrivateIp('fc00::1'))->toBeTrue();
    expect(SsrfGuard::isPrivateIp('fd00::1'))->toBeTrue();
    expect(SsrfGuard::isPrivateIp('fdff:ffff:ffff:ffff::1'))->toBeTrue();
});

it('blocks IPv6 link-local (fe80::/10)', function () {
    expect(SsrfGuard::isPrivateIp('fe80::1'))->toBeTrue();
});

it('blocks IPv4-mapped IPv6 that resolves to a private IPv4', function () {
    expect(SsrfGuard::isPrivateIp('::ffff:192.168.1.1'))->toBeTrue();
    expect(SsrfGuard::isPrivateIp('::ffff:127.0.0.1'))->toBeTrue();
    expect(SsrfGuard::isPrivateIp('::FFFF:10.0.0.1'))->toBeTrue();
});

it('allows public IPv4 addresses', function () {
    expect(SsrfGuard::isPrivateIp('1.1.1.1'))->toBeFalse();
    expect(SsrfGuard::isPrivateIp('8.8.8.8'))->toBeFalse();
    expect(SsrfGuard::isPrivateIp('104.26.10.1'))->toBeFalse();
});

it('allows public IPv6 addresses', function () {
    expect(SsrfGuard::isPrivateIp('2606:4700:4700::1111'))->toBeFalse(); // Cloudflare DNS
});

// ── assertSafe — scheme validation ────────────────────────────────────────────

it('blocks non-http schemes', function (string $url) {
    expect(fn () => SsrfGuard::assertSafe($url))
        ->toThrow(SsrfBlockedException::class);
})->with([
    'file:///etc/passwd',
    'gopher://internal-host/',
    'ftp://files.example.com/',
    'dict://internal:11211/',
]);

// ── assertSafe — IP literal in URL ────────────────────────────────────────────

it('blocks private IP literals in the URL host', function (string $url) {
    expect(fn () => SsrfGuard::assertSafe($url))
        ->toThrow(SsrfBlockedException::class);
})->with([
    'http://127.0.0.1/',
    'http://10.0.0.1/admin',
    'http://192.168.1.1/',
    'http://169.254.169.254/latest/meta-data/',
    'https://[::1]/',
    'http://[fc00::1]/',
]);

it('accepts a public IP literal in the URL host', function () {
    // 1.1.1.1 is Cloudflare DNS — public and safe.
    expect(SsrfGuard::assertSafe('https://1.1.1.1/'))->toBe('1.1.1.1');
});
