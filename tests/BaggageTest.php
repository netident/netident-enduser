<?php

declare(strict_types=1);

namespace Netident\OtelEnduser\Tests;

use Netident\OtelEnduser\Baggage;
use PHPUnit\Framework\TestCase;

final class BaggageTest extends TestCase
{
    public function testParsesNormalMember(): void
    {
        $result = Baggage::parse('app.device.id=abc-123');
        $this->assertSame(['app.device.id' => 'abc-123'], $result);
    }

    public function testParsesMultipleMergedMembers(): void
    {
        $result = Baggage::parse('app.device.id=abc-123,session.id=sess-456,other=1');
        $this->assertSame(
            ['app.device.id' => 'abc-123', 'session.id' => 'sess-456', 'other' => '1'],
            $result
        );
    }

    public function testPercentDecodesValues(): void
    {
        $result = Baggage::parse('session.id=sess%20with%20spaces');
        $this->assertSame(['session.id' => 'sess with spaces'], $result);
    }

    public function testIgnoresPropertiesAfterSemicolon(): void
    {
        $result = Baggage::parse('app.device.id=abc-123;prop1=x;prop2=y,session.id=sess-456');
        $this->assertSame(
            ['app.device.id' => 'abc-123', 'session.id' => 'sess-456'],
            $result
        );
    }

    public function testSkipsMalformedMembers(): void
    {
        $result = Baggage::parse('nokeyvalue,app.device.id=abc-123,=novalue,novalue=');
        $this->assertSame(['app.device.id' => 'abc-123'], $result);
    }

    public function testTrimsOptionalWhitespaceAroundMembers(): void
    {
        $result = Baggage::parse('  app.device.id=abc-123  ,  session.id=sess-456  ');
        $this->assertSame(
            ['app.device.id' => 'abc-123', 'session.id' => 'sess-456'],
            $result
        );
    }

    public function testCapsOversizeHeader(): void
    {
        $huge = str_repeat('a', 8192) . ',app.device.id=abc-123';
        $result = Baggage::parse($huge);
        // Header truncated to 8192 bytes before parsing; the trailing
        // well-formed member beyond the cutoff must not appear.
        $this->assertArrayNotHasKey('app.device.id', $result);
    }

    public function testCapsMemberCount(): void
    {
        $members = [];
        for ($i = 0; $i < 200; $i++) {
            $members[] = "k{$i}=v{$i}";
        }
        $header = implode(',', $members);
        $result = Baggage::parse($header);
        $this->assertLessThanOrEqual(180, count($result));
    }

    public function testEmptyHeaderReturnsEmptyArray(): void
    {
        $this->assertSame([], Baggage::parse(''));
    }
}
