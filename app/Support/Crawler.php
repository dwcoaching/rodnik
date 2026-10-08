<?php

declare(strict_types=1);

namespace App\Support;

final class Crawler
{
    // Named crawlers, the "+https://…" contact URL robots announce themselves with, and the
    // "SomethingBot/1.0" and "SomethingBot;" forms. A bare "bot" would also match phones such
    // as "CUBOT X20", and "Yandex" alone would match the Yandex app's own browser.
    private const PATTERN = '/\+https?:\/\/|googlebot|bingbot|duckduckbot|baiduspider|applebot|petalbot|ahrefsbot|semrushbot|mj12bot|dotbot|seznambot|exabot'
        .'|facebookexternalhit|facebot|twitterbot|telegrambot|slackbot|discordbot|whatsapp|linkedinbot'
        .'|gptbot|chatgpt-user|oai-searchbot|claudebot|claude-web|perplexitybot|ccbot|bytespider|amazonbot'
        .'|headlesschrome|lighthouse|crawler|spider|[a-z]bot[\/;]/i';

    public static function detect(?string $userAgent): bool
    {
        $userAgent = mb_trim((string) $userAgent);

        return $userAgent === '' || preg_match(self::PATTERN, $userAgent) === 1;
    }
}
