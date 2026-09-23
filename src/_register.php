<?php

declare(strict_types=1);

/**
 * Composer "files" autoload entry point. Runs on every request of any app
 * that requires this package — it must be inert unless everything it needs
 * is actually present, and it must never throw or emit a warning.
 */

use Netident\OtelEnduser\Config;
use Netident\OtelEnduser\CookieIssuer;
use Netident\OtelEnduser\Enricher;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanKind;

if (defined('NETIDENT_OTEL_ENDUSER_REGISTERED')) {
    return;
}
define('NETIDENT_OTEL_ENDUSER_REGISTERED', true);

if (!extension_loaded('opentelemetry')) {
    return;
}

if (PHP_SAPI === 'cli') {
    return;
}

$disabledInstrumentations = getenv('OTEL_PHP_DISABLED_INSTRUMENTATIONS');
if ($disabledInstrumentations === false) {
    $disabledInstrumentations = $_SERVER['OTEL_PHP_DISABLED_INSTRUMENTATIONS'] ?? '';
}
$disabledList = array_map('trim', explode(',', (string) $disabledInstrumentations));

if (strtolower((string) (getenv('NETIDENT_ENDUSER_DISABLED') ?: '')) === 'true') {
    return;
}
if (in_array('netident-enduser', $disabledList, true) || in_array('all', $disabledList, true)) {
    return;
}

if (!interface_exists(SpanBuilderInterface::class)) {
    return;
}

if (!function_exists('OpenTelemetry\\Instrumentation\\hook')) {
    return;
}

try {
    $config = Config::fromEnv();

    if ($config->disabled) {
        return;
    }

    \OpenTelemetry\Instrumentation\hook(
        SpanBuilderInterface::class,
        'startSpan',
        post: static function ($builder, array $params, $span) use ($config): void {
            try {
                if (!interface_exists(\OpenTelemetry\SDK\Trace\ReadableSpanInterface::class)) {
                    return;
                }
                if (!$span instanceof \OpenTelemetry\SDK\Trace\ReadableSpanInterface) {
                    return;
                }
                if ($span->getKind() !== SpanKind::KIND_SERVER) {
                    return;
                }

                // Cookies are issued once per request even when two
                // instrumentations each open a SERVER span (PSR-15 + the
                // framework's own), or the second would mint a second pair.
                // Keyed by the request's start time, because a worker-mode
                // runtime keeps statics alive across requests.
                static $issuedFor = null;
                static $issued = [];
                $marker = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
                if ($issuedFor !== $marker || $issuedFor === null) {
                    $issuedFor = $marker;
                    $issued = headers_sent() ? [] : CookieIssuer::issue(
                        $_SERVER,
                        $_COOKIE,
                        $config,
                        static function (string $name, string $value, int $expires, bool $secure): void {
                            setcookie($name, $value, [
                                'expires' => $expires,
                                'path' => '/',
                                'secure' => $secure,
                                'httponly' => false,
                                'samesite' => 'Lax',
                            ]);
                        },
                    );
                }

                $attributes = Enricher::attributes($_SERVER, $_COOKIE, $config, $issued);
                if ($attributes !== []) {
                    $span->setAttributes($attributes);
                }
            } catch (\Throwable $e) {
                // Never let instrumentation break the customer's app.
            }
        },
    );
} catch (\Throwable $e) {
    // Never let instrumentation break the customer's app.
}
