<?php

declare(strict_types=1);

use App\Support\Crawler;

test('search engines, link previews and AI crawlers are detected', function (?string $userAgent) {
    expect(Crawler::detect($userAgent))->toBeTrue();
})->with([
    'Googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'Bing' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0 Safari/537.36',
    'Yandex' => 'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)',
    'Yandex images' => 'Mozilla/5.0 (compatible; YandexImages/3.0; +http://yandex.com/bots)',
    'Petal' => 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)',
    'Telegram preview' => 'TelegramBot (like TwitterBot)',
    'Facebook preview' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
    'WhatsApp preview' => 'WhatsApp/2.23.20.0',
    'Pinterest crawler' => 'Pinterestbot/1.0 (+http://www.pinterest.com/bot.html)',
    'Sogou spider' => 'Sogou web spider/4.0(+http://www.sogou.com/docs/help/webmasters.htm#07)',
    'GPTBot' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.1; +https://openai.com/gptbot',
    'unknown bot' => 'Mozilla/5.0 (compatible; ExampleSearchBot/0.4)',
    'headless browser' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/120.0.0.0 Safari/537.36',
    'empty' => '',
    'missing' => null,
]);

test('people browsing are not crawlers, including phones with "bot" in their model name', function (string $userAgent) {
    expect(Crawler::detect($userAgent))->toBeFalse();
})->with([
    'Chrome' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
    'Safari on iPhone' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
    'Yandex Browser' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 YaBrowser/24.6.0.0 Safari/537.36',
    'Cubot phone' => 'Mozilla/5.0 (Linux; Android 9; CUBOT X20 Build/PPR1.180610.011) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
    'Firefox' => 'Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0',
    'Pinterest in-app browser' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [Pinterest/iOS]',
    'Sogou mobile browser' => 'Mozilla/5.0 (Linux; Android 10; V2001A) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.72 Mobile Safari/537.36 SogouMSE,SogouMobileBrowser/5.38.6',
    'Yandex app browser' => 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 YandexSearch/23.25.1 Mobile Safari/537.36',
]);
