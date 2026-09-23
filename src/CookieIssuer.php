<?php

declare(strict_types=1);

namespace Netident\OtelEnduser;

/**
 * Server-side identity for a PHP app that renders its own pages and runs no
 * browser script: when NETIDENT_ISSUE_COOKIES=true, a browser page load that
 * arrives without the device / session cookie gets one minted and set on the
 * response, so the package works from `composer require` alone.
 *
 * The cookie names are the browser script's own (`_nid_dev`, `_nid_ses`), so
 * a page that also loads the script continues the same ids instead of starting
 * a second pair. Not HttpOnly for the same reason — the script must read them;
 * they are random ids, not secrets.
 *
 * Only document requests are issued to: an API client, curl or a health check
 * never keeps a cookie, and minting for them would file every such request as
 * a new "device".
 */
final class CookieIssuer
{
    /** The longest a browser keeps a cookie. */
    public const DEVICE_TTL_SECONDS = 400 * 86400;
    /** Same idle gap as the browser script's default. */
    public const SESSION_IDLE_SECONDS = 15 * 60;

    /**
     * Mints whichever id is missing (or malformed), and re-sets both cookies
     * on every document request: the session cookie's expiry slides, so the
     * session ends 15 min after the last page rather than after the first.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookie
     * @param callable(string, string, int, bool): void $setCookie
     * @return array<string, string> the ids minted for this request, by cookie name
     */
    public static function issue(array $server, array $cookie, Config $config, callable $setCookie): array
    {
        if (!$config->issueCookies || !self::isBrowserDocument($server)) {
            return [];
        }

        $issued = [];
        $secure = self::isHttps($server);
        $now = time();

        foreach ([
            [$config->deviceCookieName, self::DEVICE_TTL_SECONDS],
            [$config->sessionCookieName, self::SESSION_IDLE_SECONDS],
        ] as [$name, $ttl]) {
            if ($name === null) {
                continue;
            }
            $id = $cookie[$name] ?? null;
            if (!is_string($id) || !Ids::valid($id)) {
                $id = self::uuid();
                $issued[$name] = $id;
            }
            $setCookie($name, $id, $now + $ttl, $secure);
        }

        return $issued;
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function isBrowserDocument(array $server): bool
    {
        $dest = $server['HTTP_SEC_FETCH_DEST'] ?? null;
        if (is_string($dest) && $dest !== '') {
            return $dest === 'document';
        }
        // A browser without Fetch Metadata still asks for HTML on a page load.
        $accept = $server['HTTP_ACCEPT'] ?? null;
        return is_string($accept) && str_contains($accept, 'text/html');
    }

    /**
     * Behind a TLS-terminating proxy HTTPS is unset and the cookie simply goes
     * out without Secure — marking it Secure on a plain-http site would make
     * the browser drop it.
     *
     * @param array<string, mixed> $server
     */
    private static function isHttps(array $server): bool
    {
        $https = $server['HTTPS'] ?? null;
        return is_string($https) && $https !== '' && strtolower($https) !== 'off';
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
