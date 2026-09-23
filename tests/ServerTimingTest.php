<?php

declare(strict_types=1);

namespace Netident\OtelEnduser\Tests;

use Netident\OtelEnduser\ServerTiming;
use PHPUnit\Framework\TestCase;

final class ServerTimingTest extends TestCase
{
    private const TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';
    private const SPAN_ID = '00f067aa0ba902b7';

    public function testFormatsTheHeaderValue(): void
    {
        $value = ServerTiming::headerValue(self::TRACE_ID, self::SPAN_ID);

        $this->assertSame(
            'traceparent;desc="00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01"',
            $value,
        );
    }

    public function testRejectsWrongLengthTraceId(): void
    {
        $this->assertNull(ServerTiming::headerValue(substr(self::TRACE_ID, 1), self::SPAN_ID));
        $this->assertNull(ServerTiming::headerValue(self::TRACE_ID . 'a', self::SPAN_ID));
    }

    public function testRejectsWrongLengthSpanId(): void
    {
        $this->assertNull(ServerTiming::headerValue(self::TRACE_ID, substr(self::SPAN_ID, 1)));
        $this->assertNull(ServerTiming::headerValue(self::TRACE_ID, self::SPAN_ID . 'a'));
    }

    public function testRejectsUppercaseHex(): void
    {
        $this->assertNull(ServerTiming::headerValue(strtoupper(self::TRACE_ID), self::SPAN_ID));
        $this->assertNull(ServerTiming::headerValue(self::TRACE_ID, strtoupper(self::SPAN_ID)));
    }

    public function testRejectsNonHexCharacters(): void
    {
        $this->assertNull(ServerTiming::headerValue('g' . substr(self::TRACE_ID, 1), self::SPAN_ID));
        $this->assertNull(ServerTiming::headerValue(self::TRACE_ID, 'g' . substr(self::SPAN_ID, 1)));
    }

    public function testRejectsAllZeroTraceId(): void
    {
        $this->assertNull(ServerTiming::headerValue(str_repeat('0', 32), self::SPAN_ID));
    }

    public function testRejectsAllZeroSpanId(): void
    {
        $this->assertNull(ServerTiming::headerValue(self::TRACE_ID, str_repeat('0', 16)));
    }

    public function testRejectsEmptyStrings(): void
    {
        $this->assertNull(ServerTiming::headerValue('', ''));
    }
}
