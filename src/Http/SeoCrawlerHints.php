<?php
declare(strict_types=1);

namespace Stain\Http;

/**
 * robots.txt: закрыть служебные разделы; /media/ оставить открытым (превью, картинки в выдаче).
 */
final class SeoCrawlerHints
{
    public static function robotsTxtBody(string $absoluteAppUrl): string
    {
        $base = rtrim($absoluteAppUrl, '/');
        $sitemap = $base . '/sitemap.xml';

        return <<<TXT
User-agent: *
Disallow: /panel/
Disallow: /auth
Disallow: /api/

Allow: /

Sitemap: {$sitemap}

# /media/ не в Disallow: превью ссылок и индексация иллюстраций (см. рекомендации Яндекса по графике).
TXT;
    }
}
