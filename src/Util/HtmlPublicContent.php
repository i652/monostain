<?php
declare(strict_types=1);

namespace Stain\Util;

/**
 * Улучшение HTML публичных страниц для SEO и доступности (lazy, alt).
 */
final class HtmlPublicContent
{
    public static function enhanceImagesForPublic(string $html): string
    {
        if ($html === '' || !str_contains($html, '<img')) {
            return $html;
        }

        return (string) preg_replace_callback('/<img\b[^>]*(?:\/)?>/i', static function (array $m): string {
            $tag = $m[0];
            if (!preg_match('/\sloading\s*=/i', $tag)) {
                if (preg_match('#/\s*>$#', $tag)) {
                    $tag = (string) preg_replace('#/\s*>$#', ' loading="lazy" decoding="async" />', $tag);
                } else {
                    $tag = (string) preg_replace('#>$#', ' loading="lazy" decoding="async">', $tag);
                }
            } elseif (!preg_match('/\sdecoding\s*=/i', $tag)) {
                if (preg_match('#/\s*>$#', $tag)) {
                    $tag = (string) preg_replace('#/\s*>$#', ' decoding="async" />', $tag);
                } else {
                    $tag = (string) preg_replace('#>$#', ' decoding="async">', $tag);
                }
            }
            if (preg_match('/\salt\s*=\s*"\s*"/i', $tag)) {
                $tag = (string) preg_replace('/\salt\s*=\s*"\s*"/i', 'alt="Иллюстрация"', $tag);
            } elseif (preg_match("/\salt\s*=\s*'\s*'/i", $tag)) {
                $tag = (string) preg_replace("/\salt\s*=\s*'\s*'/i", "alt='Иллюстрация'", $tag);
            } elseif (!preg_match('/\salt\s*=/i', $tag)) {
                $tag = str_ireplace('<img', '<img alt="Иллюстрация"', $tag);
            }

            return $tag;
        }, $html);
    }
}
