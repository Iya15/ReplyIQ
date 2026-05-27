<?php

namespace App\Services\Crawling;

use App\Exceptions\SsrfBlockedException;

/**
 * Guards against Server-Side Request Forgery (SSRF) attacks.
 *
 * Protection layers:
 *  1. Scheme whitelist — only http/https are allowed.
 *  2. DNS resolution — every A/AAAA record is checked against blocked CIDRs.
 *  3. Returns the first validated IP for CURLOPT_RESOLVE pinning in the caller,
 *     closing the DNS-rebinding window between check and connection.
 *
 * Critical range: 169.254.169.254/32 is the AWS instance-metadata endpoint.
 * Any request to it from the server would leak IAM credentials.
 */
final class SsrfGuard
{
    /**
     * Validate a URL against SSRF attack vectors and return the validated IP
     * so the caller can pin it via CURLOPT_RESOLVE.
     *
     * @throws SsrfBlockedException
     */
    public static function assertSafe(string $url): string
    {
        $parsed = parse_url($url);

        $scheme = strtolower($parsed['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new SsrfBlockedException("Non-HTTP scheme blocked: '{$scheme}'.");
        }

        $host = $parsed['host'] ?? '';

        if ($host === '') {
            throw new SsrfBlockedException('Empty host in URL.');
        }

        // Strip IPv6 bracket notation.
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        // If the host is already an IP literal, validate directly and return it.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (self::isPrivateIp($host)) {
                throw new SsrfBlockedException("Direct IP access blocked: {$host}.");
            }

            return $host;
        }

        // Resolve hostname → validate every returned IP.
        $ipv4 = gethostbynamel($host);
        $ipv4 = is_array($ipv4) ? $ipv4 : [];

        $aaaa = @dns_get_record($host, DNS_AAAA);
        $ipv6 = is_array($aaaa) ? array_column($aaaa, 'ipv6') : [];
        $ipv6 = array_filter($ipv6);

        $all = array_merge($ipv4, array_values($ipv6));

        if ($all === []) {
            throw new SsrfBlockedException("Could not resolve hostname: '{$host}'.");
        }

        foreach ($all as $ip) {
            if (self::isPrivateIp($ip)) {
                throw new SsrfBlockedException(
                    "URL resolves to a private/reserved address ({$ip}) — request blocked.",
                );
            }
        }

        return $ipv4[0] ?? $all[0];
    }

    /**
     * Check whether an IP address falls within any private, loopback,
     * link-local, or otherwise reserved CIDR range.
     */
    public static function isPrivateIp(string $ip): bool
    {
        // IPv4-mapped IPv6 (::ffff:x.x.x.x) — extract and check the IPv4 part.
        if (preg_match('/^::ffff:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/i', $ip, $m)) {
            return self::isPrivateIp($m[1]);
        }

        foreach (self::BLOCKED_CIDRS as [$network, $prefix]) {
            if (self::ipInCidr($ip, $network, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function ipInCidr(string $ip, string $network, int $prefix): bool
    {
        $ipBin  = inet_pton($ip);
        $netBin = inet_pton($network);

        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        $remainder = $prefix % 8;

        // Check full bytes.
        if (substr($ipBin, 0, $fullBytes) !== substr($netBin, 0, $fullBytes)) {
            return false;
        }

        // Check the partial byte (if the prefix does not fall on a byte boundary).
        if ($remainder > 0) {
            $mask    = (0xFF << (8 - $remainder)) & 0xFF;
            $ipByte  = strlen($ipBin)  > $fullBytes ? ord($ipBin[$fullBytes])  : 0;
            $netByte = strlen($netBin) > $fullBytes ? ord($netBin[$fullBytes]) : 0;

            if (($ipByte & $mask) !== ($netByte & $mask)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Blocked CIDR ranges.
     * Format: [network_address, prefix_length]
     *
     * @var array<int, array{0: string, 1: int}>
     */
    private const BLOCKED_CIDRS = [
        // ── IPv4 ──────────────────────────────────────────────────────────────
        ['0.0.0.0',           8],  // "this" network
        ['10.0.0.0',          8],  // RFC 1918 private
        ['100.64.0.0',       10],  // shared address space (RFC 6598)
        ['127.0.0.0',         8],  // loopback
        ['169.254.0.0',      16],  // link-local — AWS metadata at 169.254.169.254
        ['172.16.0.0',       12],  // RFC 1918 private
        ['192.0.0.0',        24],  // IETF protocol assignments
        ['192.168.0.0',      16],  // RFC 1918 private
        ['198.18.0.0',       15],  // benchmarking (RFC 2544)
        ['240.0.0.0',         4],  // reserved (Class E)
        ['255.255.255.255',  32],  // limited broadcast
        // ── IPv6 ──────────────────────────────────────────────────────────────
        ['::1',             128],  // loopback
        ['::ffff:0:0',       96],  // IPv4-mapped (handled above, belt-and-suspenders)
        ['fc00::',            7],  // unique local (fc00::/7 covers fd00:: too)
        ['fe80::',           10],  // link-local
        ['2001:db8::',       32],  // documentation (RFC 3849)
        ['100::',            64],  // discard (RFC 6666)
    ];
}
