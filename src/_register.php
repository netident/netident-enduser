<?php

declare(strict_types=1);

/**
 * Composer "files" autoload entry point. Runs on every request of any app
 * that requires this package — it must be inert unless everything it needs
 * is actually present, and it must never throw or emit a warning.
 */

use Netident\OtelEnduser\Config;
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

                // Computed per span, never cached: a request has about one
                // SERVER span, and anything kept in a static here would outlive
                // the request on a long-lived php-fpm worker.
                $attributes = Enricher::attributes($_SERVER, $_COOKIE, $config);
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
