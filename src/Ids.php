<?php

declare(strict_types=1);

namespace Netident\OtelEnduser;

/**
 * Validates identifiers (device id, session id) before they are ever
 * attached to a span attribute, so that an attacker-controlled header or
 * cookie cannot inject arbitrary junk (or absurdly long values) into traces.
 */
final class Ids
{
    private const PATTERN = '/^[A-Za-z0-9._:-]{1,64}$/';

    public static function valid(string $value): bool
    {
        return $value !== '' && preg_match(self::PATTERN, $value) === 1;
    }
}
