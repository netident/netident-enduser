<?php

declare(strict_types=1);

namespace Netident\OtelEnduser\Tests;

use Netident\OtelEnduser\Ids;
use PHPUnit\Framework\TestCase;

final class IdsTest extends TestCase
{
    public function testAcceptsTypicalUuid(): void
    {
        $this->assertTrue(Ids::valid('550e8400-e29b-41d4-a716-446655440000'));
    }

    public function testRejectsEmptyString(): void
    {
        $this->assertFalse(Ids::valid(''));
    }

    public function testRejectsSpaces(): void
    {
        $this->assertFalse(Ids::valid('has spaces'));
    }

    public function testRejectsOverlongValue(): void
    {
        $this->assertFalse(Ids::valid(str_repeat('a', 65)));
    }

    public function testAcceptsMaxLengthValue(): void
    {
        $this->assertTrue(Ids::valid(str_repeat('a', 64)));
    }

    public function testRejectsInjectionAttempt(): void
    {
        $this->assertFalse(Ids::valid('<script>alert(1)</script>'));
    }
}
