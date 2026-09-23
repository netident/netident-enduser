<?php

declare(strict_types=1);

namespace Netident\OtelEnduser\Tests;

use Netident\OtelEnduser\Config;
use Netident\OtelEnduser\Enricher;
use PHPUnit\Framework\TestCase;

final class EnricherTest extends TestCase
{
    private function config(array $overrides = []): Config
    {
        return new Config(
            deviceCookieName: $overrides['deviceCookieName'] ?? null,
            trustedProxyCidrs: $overrides['trustedProxyCidrs'] ?? [],
            hashClientIp: $overrides['hashClientIp'] ?? false,
            ipHashSalt: $overrides['ipHashSalt'] ?? '',
            disabled: $overrides['disabled'] ?? false,
        );
    }

    public function testDeviceIdPrecedenceBaggageOverHeaderOverCookie(): void
    {
        $server = [
            'HTTP_BAGGAGE' => 'app.device.id=from-baggage',
            'HTTP_X_DEVICE_ID' => 'from-header',
            'REMOTE_ADDR' => '203.0.113.1',
        ];
        $cookie = ['device_id' => 'from-cookie'];
        $config = $this->config(['deviceCookieName' => 'device_id']);

        $attrs = Enricher::attributes($server, $cookie, $config);

        $this->assertSame('from-baggage', $attrs['app.device.id']);
        $this->assertSame('baggage', $attrs['app.device.id.source']);
    }

    public function testDeviceIdFallsBackToHeaderWhenNoBaggage(): void
    {
        $server = [
            'HTTP_X_DEVICE_ID' => 'from-header',
            'REMOTE_ADDR' => '203.0.113.1',
        ];
        $cookie = ['device_id' => 'from-cookie'];
        $config = $this->config(['deviceCookieName' => 'device_id']);

        $attrs = Enricher::attributes($server, $cookie, $config);

        $this->assertSame('from-header', $attrs['app.device.id']);
        $this->assertSame('header', $attrs['app.device.id.source']);
    }

    public function testDeviceIdFallsBackToCookieWhenNoBaggageOrHeader(): void
    {
        $server = ['REMOTE_ADDR' => '203.0.113.1'];
        $cookie = ['device_id' => 'from-cookie'];
        $config = $this->config(['deviceCookieName' => 'device_id']);

        $attrs = Enricher::attributes($server, $cookie, $config);

        $this->assertSame('from-cookie', $attrs['app.device.id']);
        $this->assertSame('cookie', $attrs['app.device.id.source']);
    }

    public function testCookieIgnoredWhenNoCookieNameConfigured(): void
    {
        $server = ['REMOTE_ADDR' => '203.0.113.1'];
        $cookie = ['device_id' => 'from-cookie'];
        $config = $this->config(['deviceCookieName' => null]);

        $attrs = Enricher::attributes($server, $cookie, $config);

        $this->assertArrayNotHasKey('app.device.id', $attrs);
        $this->assertArrayNotHasKey('app.device.id.source', $attrs);
    }

    public function testInvalidIdsAreDropped(): void
    {
        $server = [
            'HTTP_BAGGAGE' => 'app.device.id=' . rawurlencode('bad id with spaces & junk!'),
            'REMOTE_ADDR' => '203.0.113.1',
        ];
        $config = $this->config();

        $attrs = Enricher::attributes($server, [], $config);

        $this->assertArrayNotHasKey('app.device.id', $attrs);
        $this->assertArrayNotHasKey('app.device.id.source', $attrs);
    }

    public function testSessionIdFromBaggage(): void
    {
        $server = [
            'HTTP_BAGGAGE' => 'session.id=sess-789',
            'REMOTE_ADDR' => '203.0.113.1',
        ];
        $config = $this->config();

        $attrs = Enricher::attributes($server, [], $config);

        $this->assertSame('sess-789', $attrs['session.id']);
    }

    public function testInvalidSessionIdDropped(): void
    {
        $server = [
            'HTTP_BAGGAGE' => 'session.id=' . rawurlencode('has spaces'),
            'REMOTE_ADDR' => '203.0.113.1',
        ];
        $config = $this->config();

        $attrs = Enricher::attributes($server, [], $config);

        $this->assertArrayNotHasKey('session.id', $attrs);
    }

    public function testClientAddressPlain(): void
    {
        $server = ['REMOTE_ADDR' => '203.0.113.1'];
        $config = $this->config();

        $attrs = Enricher::attributes($server, [], $config);

        $this->assertSame('203.0.113.1', $attrs['client.address']);
    }

    public function testClientAddressHashedWithSalt(): void
    {
        $server = ['REMOTE_ADDR' => '203.0.113.1'];
        $config = $this->config(['hashClientIp' => true, 'ipHashSalt' => 'pepper']);

        $attrs = Enricher::attributes($server, [], $config);

        $expected = substr(hash('sha256', 'pepper' . '203.0.113.1'), 0, 32);
        $this->assertSame($expected, $attrs['client.address']);
        $this->assertSame(32, strlen($attrs['client.address']));
    }

    public function testClientAddressHashedWithEmptySalt(): void
    {
        $server = ['REMOTE_ADDR' => '203.0.113.1'];
        $config = $this->config(['hashClientIp' => true, 'ipHashSalt' => '']);

        $attrs = Enricher::attributes($server, [], $config);

        $expected = substr(hash('sha256', '203.0.113.1'), 0, 32);
        $this->assertSame($expected, $attrs['client.address']);
    }

    public function testNoAttributesWhenNothingResolvable(): void
    {
        $config = $this->config();
        $attrs = Enricher::attributes([], [], $config);

        $this->assertArrayNotHasKey('app.device.id', $attrs);
        $this->assertArrayNotHasKey('session.id', $attrs);
        $this->assertArrayNotHasKey('client.address', $attrs);
    }
}
