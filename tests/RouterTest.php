<?php
declare(strict_types=1);

use Stain\Http\Router;
use Stain\Http\SeoCrawlerHints;

final class RouterTest
{
    public static function run(): void
    {
        self::testDispatchInvokesHandler();
        self::testFalseContinuesToNextRoute();
        self::testRobotsTxtBody();
        self::testW3cLastmod();
    }

    private static function assertTrue(bool $cond, string $msg = ''): void
    {
        if (!$cond) {
            throw new \RuntimeException('Assertion failed' . ($msg !== '' ? ': ' . $msg : ''));
        }
    }

    private static function testDispatchInvokesHandler(): void
    {
        $r = new Router();
        $called = false;
        $r->map('GET', '#^/$#', function () use (&$called): void {
            $called = true;
        });
        self::assertTrue($r->dispatch('GET', '/'));
        self::assertTrue($called);
        self::assertTrue(!$r->dispatch('GET', '/missing'));
    }

    private static function testFalseContinuesToNextRoute(): void
    {
        $r = new Router();
        $second = false;
        $r->map('GET', '#^/panel$#', fn () => false);
        $r->map('GET', '#^/panel$#', function () use (&$second): void {
            $second = true;
        });
        self::assertTrue($r->dispatch('GET', '/panel'));
        self::assertTrue($second);
    }

    private static function testRobotsTxtBody(): void
    {
        $b = SeoCrawlerHints::robotsTxtBody('https://example.org');
        self::assertTrue(str_contains($b, 'Disallow: /panel/'), 'robots Disallow panel');
        self::assertTrue(str_contains($b, 'Disallow: /api/'), 'robots Disallow api');
        self::assertTrue(str_contains($b, 'Sitemap: https://example.org/sitemap.xml'), 'robots Sitemap');
    }

    private static function testW3cLastmod(): void
    {
        $lm = \Stain\Http\PublicDocumentHeaders::w3cLastmodFromTimestamps(
            '2020-01-02 00:00:00+00',
            '2020-01-01 00:00:00+00',
            '2019-12-31 00:00:00+00',
        );
        self::assertTrue(str_starts_with($lm, '2020-01-02'), 'lastmod picks latest');
    }
}
