<?php

declare(strict_types=1);

namespace Netident\OtelEnduser;

/**
 * Reads this package's environment configuration once.
 */
final class Config
{
    public function __construct(
        public readonly ?string $deviceCookieName,
        public readonly ?string $sessionCookieName,
        /** @var string[] */
        public readonly array $trustedProxyCidrs,
        public readonly bool $hashClientIp,
        public readonly string $ipHashSalt,
        public readonly bool $disabled,
        public readonly bool $issueCookies = false,
    ) {
    }

    public static function fromEnv(): self
    {
        $trustedProxies = self::env('NETIDENT_TRUSTED_PROXIES') ?? '';
        $hashClientIp = self::env('NETIDENT_HASH_CLIENT_IP') ?? '';
        $salt = self::env('NETIDENT_IP_HASH_SALT') ?? '';
        $disabled = self::env('NETIDENT_ENDUSER_DISABLED') ?? '';
        $issueCookies = self::env('NETIDENT_ISSUE_COOKIES') ?? '';

        $cidrs = array_values(array_filter(array_map('trim', explode(',', $trustedProxies)), static fn ($v) => $v !== ''));

        return new self(
            deviceCookieName: self::cookieName(self::env('NETIDENT_DEVICE_COOKIE'), '_nid_dev'),
            sessionCookieName: self::cookieName(self::env('NETIDENT_SESSION_COOKIE'), '_nid_ses'),
            trustedProxyCidrs: $cidrs,
            hashClientIp: self::isTruthy($hashClientIp),
            ipHashSalt: $salt,
            disabled: self::isTruthy($disabled),
            issueCookies: self::isTruthy($issueCookies),
        );
    }

    /**
     * The browser script writes `_nid_dev` / `_nid_ses`, so those are read by
     * default; the env var renames the cookie, and `off` stops reading it.
     */
    private static function cookieName(?string $value, string $default): ?string
    {
        if ($value === null) {
            return $default;
        }
        return strtolower(trim($value)) === 'off' ? null : $value;
    }

    private static function isTruthy(string $value): bool
    {
        return strtolower(trim($value)) === 'true';
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            return $value;
        }
        if (isset($_SERVER[$name]) && $_SERVER[$name] !== '') {
            return (string) $_SERVER[$name];
        }
        return null;
    }
}
