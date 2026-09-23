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
            'NETIDENT_SESSION_COOKIE',
            'NETIDENT_TRUSTED_PROXIES',
            'NETIDENT_HASH_CLIENT_IP',
            'NETIDENT_IP_HASH_SALT',
            'NETIDENT_ENDUSER_DISABLED',
            'NETIDENT_ISSUE_COOKIES',
            'NETIDENT_SERVER_TIMING',
        ] as $name) {
            putenv($name);
            unset($_SERVER[$name]);
        }
    }

    public function testDefaults(): void
    {
        $config = Config::fromEnv();

        $this->assertSame('_nid_dev', $config->deviceCookieName);
        $this->assertSame('_nid_ses', $config->sessionCookieName);
        $this->assertSame([], $config->trustedProxyCidrs);
        $this->assertFalse($config->hashClientIp);
        $this->assertSame('', $config->ipHashSalt);
        $this->assertFalse($config->disabled);
        $this->assertFalse($config->issueCookies);
        $this->assertTrue($config->serverTiming);
    }

    public function testIssueCookiesIsOptIn(): void
    {
        putenv('NETIDENT_ISSUE_COOKIES=true');

        $this->assertTrue(Config::fromEnv()->issueCookies);
    }

    public function testServerTimingIsOnByDefaultAndCanBeTurnedOff(): void
    {
        foreach (['off', 'false', '0', 'OFF'] as $value) {
            putenv("NETIDENT_SERVER_TIMING=$value");
            $this->assertFalse(Config::fromEnv()->serverTiming, "'$value' should disable it");
        }

        putenv('NETIDENT_SERVER_TIMING=true');
        $this->assertTrue(Config::fromEnv()->serverTiming);
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

    public function testCookiesCanBeTurnedOff(): void
    {
        putenv('NETIDENT_DEVICE_COOKIE=off');
        putenv('NETIDENT_SESSION_COOKIE=OFF');

        $config = Config::fromEnv();

        $this->assertNull($config->deviceCookieName);
        $this->assertNull($config->sessionCookieName);
    }

    public function testFallsBackToServerSuperglobal(): void
    {
        $_SERVER['NETIDENT_DEVICE_COOKIE'] = 'nid_device';

        $config = Config::fromEnv();

        $this->assertSame('nid_device', $config->deviceCookieName);
    }
}
