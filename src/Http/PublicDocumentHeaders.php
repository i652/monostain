<?php
declare(strict_types=1);

namespace Stain\Http;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Last-Modified для публичных HTML и условный ответ 304.
 */
final class PublicDocumentHeaders
{
    /**
     * @param non-empty-string|null $dbTimestamp значение из PostgreSQL (timestamptz)
     */
    public static function maybeSend304AndExit(?string $dbTimestamp): void
    {
        if ($dbTimestamp === null || $dbTimestamp === '') {
            return;
        }

        $dt = self::parseUtc($dbTimestamp);
        if ($dt === null) {
            return;
        }

        $ims = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        if ($ims !== '') {
            $clientTs = strtotime($ims);
            if ($clientTs !== false && $dt->getTimestamp() <= $clientTs) {
                http_response_code(304);
                exit();
            }
        }

        header('Last-Modified: ' . $dt->format('D, d M Y H:i:s') . ' GMT');
    }

    /**
     * @param non-empty-string|null $dbTimestamp
     */
    public static function sendLastModifiedOnly(?string $dbTimestamp): void
    {
        if ($dbTimestamp === null || $dbTimestamp === '') {
            return;
        }
        $dt = self::parseUtc($dbTimestamp);
        if ($dt === null) {
            return;
        }
        header('Last-Modified: ' . $dt->format('D, d M Y H:i:s') . ' GMT');
    }

    /** W3C / sitemap lastmod (UTC Z). */
    public static function w3cLastmodFromTimestamps(?string $updatedAt, ?string $publishedAt, ?string $createdAt): string
    {
        $best = 0;
        foreach ([$updatedAt, $publishedAt, $createdAt] as $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }
            $ts = strtotime($raw);
            if ($ts !== false && $ts > $best) {
                $best = $ts;
            }
        }
        if ($best === 0) {
            $best = time();
        }

        return gmdate('Y-m-d\TH:i:s\Z', $best);
    }

    /** Одна метка времени (например, для главной в sitemap). */
    public static function w3cLastmodFromOptional(?string $timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return gmdate('Y-m-d\TH:i:s\Z');
        }
        $ts = strtotime($timestamp);

        return gmdate('Y-m-d\TH:i:s\Z', $ts !== false ? $ts : time());
    }

    private static function parseUtc(string $dbTimestamp): ?DateTimeImmutable
    {
        try {
            $dt = new DateTimeImmutable($dbTimestamp);
        } catch (\Throwable) {
            return null;
        }

        return $dt->setTimezone(new DateTimeZone('UTC'));
    }
}
