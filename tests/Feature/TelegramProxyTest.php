<?php

declare(strict_types=1);

use NotificationChannels\Telegram\Telegram;
use NotificationChannels\Telegram\TelegramMessage;

test('the telegram bot api goes through the configured proxy', function () {
    config(['services.telegram-bot-api.proxy' => 'http://user:secret@proxy.test:3128']);

    $http = (function () {
        return $this->http;
    })->call(app(Telegram::class));

    expect($http->getConfig('proxy'))->toBe('http://user:secret@proxy.test:3128');
});

test('the telegram bot api connects directly when no proxy is configured', function () {
    config(['services.telegram-bot-api.proxy' => null]);

    $http = (function () {
        return $this->http;
    })->call(app(Telegram::class));

    expect($http->getConfig('proxy'))->toBeNull();
});

test('telegram messages are sent with the configured bot and proxy', function () {
    config([
        'services.telegram-bot-api.token' => '123:abc',
        'services.telegram-bot-api.proxy' => 'http://proxy.test:3128',
    ]);

    $telegram = TelegramMessage::create('Test')->telegram;

    $http = (function () {
        return $this->http;
    })->call($telegram);

    expect($telegram->getToken())->toBe('123:abc')
        ->and($telegram->getApiBaseUri())->toBe('https://api.telegram.org')
        ->and($http->getConfig('proxy'))->toBe('http://proxy.test:3128');
});
