<?php

declare(strict_types=1);

namespace Netident\OtelEnduser;

/**
 * Resolves the real client address from $_SERVER, honouring
 * X-Forwarded-For only when the immediate peer (REMOTE_ADDR) is a trusted
 * proxy, and walking the XFF chain right-to-left skipping trusted hops.
 */
final class ClientIp
{
    /**
     * @param array<string, mixed> $server
     * @param string[] $trustedCidrs CIDR strings (v4 and/or v6)
     */
    public static function resolve(array $server, array $trustedCidrs): ?string
    {
        $remoteAddr = isset($server['REMOTE_ADDR']) ? (string) $server['REMOTE_ADDR'] : '';
        $remoteAddr = self::sanitize($remoteAddr);

        if ($remoteAddr === null) {
            return null;
        }

        if (empty($trustedCidrs) || !self::isTrusted($remoteAddr, $trustedCidrs)) {
            return $remoteAddr;
        }

        $xff = isset($server['HTTP_X_FORWARDED_FOR']) ? (string) $server['HTTP_X_FORWARDED_FOR'] : '';
        if ($xff === '') {
            return $remoteAddr;
        }

        $hops = array_map('trim', explode(',', $xff));
        // Walk right to left: the entry closest to us (rightmost) is checked
        // first; skip every hop that is itself a trusted proxy; the first
        // untrusted hop we find is the real client.
        for ($i = count($hops) - 1; $i >= 0; $i--) {
            $candidate = self::sanitize($hops[$i]);
            if ($candidate === null) {
                // Garbage entry — stop walking, fall back to what we trusted so far.
                break;
            }
            if (self::isTrusted($candidate, $trustedCidrs)) {
                continue;
            }
            return $candidate;
        }

        return $remoteAddr;
    }

    private static function sanitize(string $ip): ?string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return null;
        }
        // Strip a bracketed IPv6 literal e.g. "[::1]" -> "::1"
        if ($ip[0] === '[' && str_ends_with($ip, ']')) {
            $ip = substr($ip, 1, -1);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        return $ip;
    }

    /**
     * @param string[] $trustedCidrs
     */
    private static function isTrusted(string $ip, array $trustedCidrs): bool
    {
        foreach ($trustedCidrs as $cidr) {
            if (self::ipInCidr($ip, trim($cidr))) {
                return true;
            }
        }
        return false;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        if ($cidr === '') {
            return false;
        }

        if (!str_contains($cidr, '/')) {
            // Bare IP, treat as an exact match.
            return self::sanitize($cidr) === $ip;
        }

        [$subnet, $maskStr] = explode('/', $cidr, 2);
        $subnet = self::sanitize($subnet);
        if ($subnet === null || !ctype_digit($maskStr)) {
            return false;
        }
        $mask = (int) $maskStr;

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false) {
            return false;
        }
        if (strlen($ipBin) !== strlen($subnetBin)) {
            return false; // v4 vs v6 mismatch
        }

        $bits = strlen($ipBin) * 8;
        if ($mask < 0 || $mask > $bits) {
            return false;
        }

        $bytes = intdiv($mask, 8);
        $remainderBits = $mask % 8;

        if ($bytes > 0 && strncmp($ipBin, $subnetBin, $bytes) !== 0) {
            return false;
        }

        if ($remainderBits === 0) {
            return true;
        }

        $mask8 = (~(0xFF >> $remainderBits)) & 0xFF;
        $ipByte = ord($ipBin[$bytes]);
        $subnetByte = ord($subnetBin[$bytes]);

        return ($ipByte & $mask8) === ($subnetByte & $mask8);
    }
}
