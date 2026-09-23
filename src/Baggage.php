<?php

declare(strict_types=1);

namespace Netident\OtelEnduser;

/**
 * Minimal W3C Baggage (https://www.w3.org/TR/baggage/) parser.
 *
 * Only what this package needs: a flat map of member name => decoded value.
 * Properties (";key=value" after the value) are recognised but discarded —
 * we only ever read `app.device.id` and `session.id` values.
 */
final class Baggage
{
    private const MAX_HEADER_BYTES = 8192;
    private const MAX_MEMBERS = 180;

    /**
     * @return array<string, string>
     */
    public static function parse(string $header): array
    {
        if (strlen($header) > self::MAX_HEADER_BYTES) {
            $header = substr($header, 0, self::MAX_HEADER_BYTES);
        }

        $result = [];
        $members = explode(',', $header);

        $count = 0;
        foreach ($members as $member) {
            if ($count >= self::MAX_MEMBERS) {
                break;
            }
            $count++;

            $member = trim($member, " \t");
            if ($member === '') {
                continue;
            }

            // Strip properties: "key=value;prop1=a;prop2=b" -> "key=value"
            $semi = strpos($member, ';');
            $keyValue = $semi === false ? $member : substr($member, 0, $semi);

            $eq = strpos($keyValue, '=');
            if ($eq === false) {
                continue; // malformed member, skip
            }

            $key = trim(substr($keyValue, 0, $eq), " \t");
            $rawValue = trim(substr($keyValue, $eq + 1), " \t");

            if ($key === '' || $rawValue === '') {
                continue;
            }

            $decoded = rawurldecode($rawValue);
            if ($decoded === '' && $rawValue !== '') {
                continue; // decode failure guard (rawurldecode rarely fails, but be safe)
            }

            $result[$key] = $decoded;
        }

        return $result;
    }
}
