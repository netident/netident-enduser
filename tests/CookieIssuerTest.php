<?php

declare(strict_types=1);

namespace Netident\OtelEnduser\Tests;

use Netident\OtelEnduser\Config;
use Netident\OtelEnduser\CookieIssuer;
use PHPUnit\Framework\TestCase;

final class CookieIssuerTest extends TestCase
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    /** @var list<array{0: string, 1: string, 2: int, 3: bool}> */
    private array $calls = [];

    private function config(bool $issue = true, ?string $dev = '_nid_dev', ?string $ses = '_nid_ses'): Config
    {
        return new Config(
            deviceCookieName: $dev,
            sessionCookieName: $ses,
            trustedProxyCidrs: [],
            hashClientIp: false,
            ipHashSalt: '',
            disabled: false,
            issueCookies: $issue,
        );
    }

    private function issue(array $server, array $cookie, Config $config): array
    {
        return CookieIssuer::issue($server, $cookie, $config, function (string $n, string $v, int $e, bool $s): void {
            $this->calls[] = [$n, $v, $e, $s];
        });
    }

    public function testOffByDefault(): void
    {
        $issued = $this->issue(['HTTP_SEC_FETCH_DEST' => 'document'], [], $this->config(issue: false));

        $this->assertSame([], $issued);
        $this->assertSame([], $this->calls);
    }

    public function testNotIssuedToNonDocumentRequests(): void
    {
        $this->issue(['HTTP_SEC_FETCH_DEST' => 'empty'], [], $this->config());
        $this->issue(['HTTP_ACCEPT' => 'application/json'], [], $this->config());
        $this->issue([], [], $this->config()); // curl, health checks

        $this->assertSame([], $this->calls);
    }

    public function testMintsBothOnAFirstPageLoad(): void
    {
        $issued = $this->issue(['HTTP_SEC_FETCH_DEST' => 'document'], [], $this->config());

        $this->assertMatchesRegularExpression(self::UUID, $issued['_nid_dev']);
        $this->assertMatchesRegularExpression(self::UUID, $issued['_nid_ses']);
        $this->assertCount(2, $this->calls);
        $this->assertFalse($this->calls[0][3], 'plain http must not be Secure');
    }

    public function testAcceptHtmlCountsWithoutFetchMetadata(): void
    {
        $issued = $this->issue(['HTTP_ACCEPT' => 'text/html,application/xhtml+xml'], [], $this->config());

        $this->assertArrayHasKey('_nid_dev', $issued);
    }

    public function testExistingIdsAreKeptAndTheSessionSlides(): void
    {
        $cookie = ['_nid_dev' => 'dev-1', '_nid_ses' => 'ses-1'];

        $issued = $this->issue(['HTTP_SEC_FETCH_DEST' => 'document'], $cookie, $this->config());

        $this->assertSame([], $issued);
        $this->assertSame(['_nid_dev', 'dev-1'], array_slice($this->calls[0], 0, 2));
        $this->assertSame(['_nid_ses', 'ses-1'], array_slice($this->calls[1], 0, 2));
        $this->assertEqualsWithDelta(time() + CookieIssuer::SESSION_IDLE_SECONDS, $this->calls[1][2], 2);
    }

    public function testMalformedCookieIsReplaced(): void
    {
        $issued = $this->issue(['HTTP_SEC_FETCH_DEST' => 'document'], ['_nid_dev' => 'not a valid id!'], $this->config());

        $this->assertMatchesRegularExpression(self::UUID, $issued['_nid_dev']);
    }

    public function testSecureOnHttps(): void
    {
        $this->issue(['HTTP_SEC_FETCH_DEST' => 'document', 'HTTPS' => 'on'], [], $this->config());

        $this->assertTrue($this->calls[0][3]);
    }

    public function testACookieTurnedOffIsNeverSet(): void
    {
        $issued = $this->issue(['HTTP_SEC_FETCH_DEST' => 'document'], [], $this->config(ses: null));

        $this->assertArrayNotHasKey('_nid_ses', $issued);
        $this->assertCount(1, $this->calls);
    }
}
