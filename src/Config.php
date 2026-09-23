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
        /** @var string[] */
        public readonly array $trustedProxyCidrs,
        public readonly bool $hashClientIp,
        public readonly string $ipHashSalt,
        public readonly bool $disabled,
    ) {
    }

    public static function fromEnv(): self
    {
        $deviceCookie = self::env('NETIDENT_DEVICE_COOKIE');
        $trustedProxies = self::env('NETIDENT_TRUSTED_PROXIES') ?? '';
        $hashClientIp = self::env('NETIDENT_HASH_CLIENT_IP') ?? '';
        $salt = self::env('NETIDENT_IP_HASH_SALT') ?? '';
        $disabled = self::env('NETIDENT_ENDUSER_DISABLED') ?? '';

        $cidrs = array_values(array_filter(array_map('trim', explode(',', $trustedProxies)), static fn ($v) => $v !== ''));

        return new self(
            deviceCookieName: ($deviceCookie !== null && $deviceCookie !== '') ? $deviceCookie : null,
            trustedProxyCidrs: $cidrs,
            hashClientIp: self::isTruthy($hashClientIp),
            ipHashSalt: $salt,
            disabled: self::isTruthy($disabled),
        );
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
