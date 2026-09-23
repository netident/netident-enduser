<?php

declare(strict_types=1);

namespace Netident\OtelEnduser;

/**
 * Resolves the end-user attribute map for the current request.
 *
 * Pure and unit-testable: takes $_SERVER / $_COOKIE snapshots plus a
 * resolved Config, and returns the attributes to attach to SERVER spans.
 * Does not depend on OpenTelemetry itself.
 */
final class Enricher
{
    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookie
     * @return array<string, string>
     */
    public static function attributes(array $server, array $cookie, Config $config): array
    {
        $attributes = [];

        $baggage = self::parseBaggage($server);

        [$deviceId, $deviceSource] = self::resolveDeviceId($server, $cookie, $config, $baggage);
        if ($deviceId !== null) {
            $attributes['app.device.id'] = $deviceId;
            $attributes['app.device.id.source'] = $deviceSource;
        }

        $sessionId = $baggage['session.id'] ?? null;
        if ($sessionId !== null && Ids::valid($sessionId)) {
            $attributes['session.id'] = $sessionId;
        }

        $clientAddress = self::resolveClientAddress($server, $config);
        if ($clientAddress !== null) {
            $attributes['client.address'] = $clientAddress;
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function parseBaggage(array $server): array
    {
        $header = $server['HTTP_BAGGAGE'] ?? null;
        if (!is_string($header) || $header === '') {
            return [];
        }
        return Baggage::parse($header);
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookie
     * @param array<string, string> $baggage
     * @return array{0: ?string, 1: string}
     */
    private static function resolveDeviceId(array $server, array $cookie, Config $config, array $baggage): array
    {
        $fromBaggage = $baggage['app.device.id'] ?? null;
        if (is_string($fromBaggage) && Ids::valid($fromBaggage)) {
            return [$fromBaggage, 'baggage'];
        }

        $fromHeader = $server['HTTP_X_DEVICE_ID'] ?? null;
        if (is_string($fromHeader) && Ids::valid($fromHeader)) {
            return [$fromHeader, 'header'];
        }

        if ($config->deviceCookieName !== null) {
            $fromCookie = $cookie[$config->deviceCookieName] ?? null;
            if (is_string($fromCookie) && Ids::valid($fromCookie)) {
                return [$fromCookie, 'cookie'];
            }
        }

        return [null, ''];
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function resolveClientAddress(array $server, Config $config): ?string
    {
        $ip = ClientIp::resolve($server, $config->trustedProxyCidrs);
        if ($ip === null) {
            return null;
        }

        if (!$config->hashClientIp) {
            return $ip;
        }

        $hash = hash('sha256', $config->ipHashSalt . $ip);
        return substr($hash, 0, 32);
    }
}
