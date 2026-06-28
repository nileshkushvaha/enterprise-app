<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lightweight user-agent parser — no external dependency required.
 * For production use with high accuracy, swap with jenssegers/agent or browscap.
 */
final class UserAgentParser
{
    public static function parse(string $userAgent): array
    {
        return [
            'browser' => self::detectBrowser($userAgent),
            'platform' => self::detectPlatform($userAgent),
            'device_type' => self::detectDeviceType($userAgent),
        ];
    }

    private static function detectBrowser(string $ua): string
    {
        $browsers = [
            'Edg' => 'Edge',
            'OPR' => 'Opera',
            'Opera' => 'Opera',
            'Chrome' => 'Chrome',
            'Firefox' => 'Firefox',
            'Safari' => 'Safari',
            'MSIE' => 'Internet Explorer',
            'Trident' => 'Internet Explorer',
        ];

        foreach ($browsers as $key => $name) {
            if (str_contains($ua, $key)) {
                return $name;
            }
        }

        return 'Unknown';
    }

    private static function detectPlatform(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Windows NT') => 'Windows',
            str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'iPhone') => 'iOS',
            str_contains($ua, 'iPad') => 'iPadOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown',
        };
    }

    private static function detectDeviceType(string $ua): string
    {
        if (str_contains($ua, 'Mobile') || str_contains($ua, 'iPhone')) {
            return 'mobile';
        }

        if (str_contains($ua, 'iPad') || str_contains($ua, 'Tablet')) {
            return 'tablet';
        }

        if (str_contains($ua, 'bot') || str_contains($ua, 'crawl') || str_contains($ua, 'spider')) {
            return 'bot';
        }

        return 'desktop';
    }
}
