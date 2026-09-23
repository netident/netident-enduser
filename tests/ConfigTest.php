<?php

declare(strict_types=1);

namespace Netident\OtelEnduser\Tests;

use Netident\OtelEnduser\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach ([
            'NETIDENT_DEVICE_COOKIE',
            'NETIDENT_TRUSTED_PROXIES',
            'NETIDENT_HASH_CLIENT_IP',
            'NETIDENT_IP_HASH_SALT',
            'NETIDENT_ENDUSER_DISABLED',
        ] as $name) {
            putenv($name);
            unset($_SERVER[$name]);
        }
    }

    public function testDefaults(): void
    {
        $config = Config::fromEnv();

        $this->assertNull($config->deviceCookieName);
        $this->assertSame([], $config->trustedProxyCidrs);
        $this->assertFalse($config->hashClientIp);
        $this->assertSame('', $config->ipHashSalt);
        $this->assertFalse($config->disabled);
    }

    public function testReadsFromGetenv(): void
    {
        putenv('NETIDENT_DEVICE_COOKIE=nid_device');
        putenv('NETIDENT_TRUSTED_PROXIES=10.0.0.0/8, 192.168.0.0/16');
        putenv('NETIDENT_HASH_CLIENT_IP=true');

        $config = Config::fromEnv();

        $this->assertSame('nid_device', $config->deviceCookieName);
        $this->assertSame(['10.0.0.0/8', '192.168.0.0/16'], $config->trustedProxyCidrs);
        $this->assertTrue($config->hashClientIp);
    }

    public function testFallsBackToServerSuperglobal(): void
    {
        $_SERVER['NETIDENT_DEVICE_COOKIE'] = 'nid_device';

        $config = Config::fromEnv();

        $this->assertSame('nid_device', $config->deviceCookieName);
    }
}
