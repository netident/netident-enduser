<?php

declare(strict_types=1);

namespace Netident\OtelEnduser\Tests;

use Netident\OtelEnduser\ClientIp;
use PHPUnit\Framework\TestCase;

final class ClientIpTest extends TestCase
{
    public function testNoProxyReturnsRemoteAddr(): void
    {
        $server = ['REMOTE_ADDR' => '203.0.113.10'];
        $this->assertSame('203.0.113.10', ClientIp::resolve($server, []));
    }

    public function testXffIgnoredWhenRemoteAddrNotTrusted(): void
    {
        $server = [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.5',
        ];
        // 203.0.113.10 is not in the trusted list, so REMOTE_ADDR wins.
        $this->assertSame('203.0.113.10', ClientIp::resolve($server, ['10.0.0.0/8']));
    }

    public function testTrustedProxyChainV4(): void
    {
        $server = [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.5, 10.0.0.9, 10.0.0.5',
        ];
        // Walk right to left: 10.0.0.5 and 10.0.0.9 are trusted hops,
        // 198.51.100.5 is the first untrusted entry => the real client.
        $this->assertSame('198.51.100.5', ClientIp::resolve($server, ['10.0.0.0/8']));
    }

    public function testTrustedProxyV6Cidr(): void
    {
        $server = [
            'REMOTE_ADDR' => '2001:db8::1',
            'HTTP_X_FORWARDED_FOR' => '2001:4860:4860::8888, 2001:db8::1',
        ];
        $this->assertSame(
            '2001:4860:4860::8888',
            ClientIp::resolve($server, ['2001:db8::/32'])
        );
    }

    public function testGarbageXffFallsBackToRemoteAddr(): void
    {
        $server = [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => 'not-an-ip, also garbage',
        ];
        $this->assertSame('10.0.0.5', ClientIp::resolve($server, ['10.0.0.0/8']));
    }

    public function testMissingRemoteAddrReturnsNull(): void
    {
        $this->assertNull(ClientIp::resolve([], ['10.0.0.0/8']));
    }

    public function testInvalidRemoteAddrReturnsNull(): void
    {
        $this->assertNull(ClientIp::resolve(['REMOTE_ADDR' => 'not-an-ip'], []));
    }

    public function testEntirelyTrustedChainFallsBackToRemoteAddr(): void
    {
        $server = [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '10.0.0.9, 10.0.0.5',
        ];
        $this->assertSame('10.0.0.5', ClientIp::resolve($server, ['10.0.0.0/8']));
    }
}
