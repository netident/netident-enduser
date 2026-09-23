<?php

declare(strict_types=1);

namespace Netident\OtelEnduser;

/**
 * Formats the `Server-Timing: traceparent;desc="00-<trace>-<span>-01"`
 * response header value (see docs/enduser-sdk.md, "Page load ↔ trace"). The
 * browser reads it from the page's navigation-timing entry and from every
 * fetch/XHR's resource-timing entry, which is how a page load — and every
 * API call it makes, sampled or not — gets linked to its backend trace. It
 * names the trace only: no timings, no user data.
 *
 * Pure and unit-testable: no OpenTelemetry types, no I/O, no globals.
 */
final class ServerTiming
{
    private const TRACE_ID_PATTERN = '/^[0-9a-f]{32}$/';
    private const SPAN_ID_PATTERN = '/^[0-9a-f]{16}$/';

    /**
     * Returns the `Server-Timing` header VALUE (everything after the colon;
     * the caller adds the header name), or null when either id fails
     * validation: not lowercase hex of the right length, or all-zero (the
     * id OpenTelemetry itself uses for an invalid/absent context).
     */
    public static function headerValue(string $traceId, string $spanId): ?string
    {
        if (!self::isValidHexId($traceId, self::TRACE_ID_PATTERN)) {
            return null;
        }
        if (!self::isValidHexId($spanId, self::SPAN_ID_PATTERN)) {
            return null;
        }

        return sprintf('traceparent;desc="00-%s-%s-01"', $traceId, $spanId);
    }

    private static function isValidHexId(string $value, string $pattern): bool
    {
        if (preg_match($pattern, $value) !== 1) {
            return false;
        }
        // preg_match above already guarantees an all-hex string of the
        // right length; a hit here means at least one non-zero digit.
        return preg_match('/[1-9a-f]/', $value) === 1;
    }
}
