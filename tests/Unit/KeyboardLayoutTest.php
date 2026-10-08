<?php

declare(strict_types=1);

use App\Services\Geography\KeyboardLayout;

test('text typed with the wrong layout is retyped on the other one', function (string $typed, string $expected) {
    expect(KeyboardLayout::swap($typed))->toBe($expected);
})->with([
    'lowercase Latin' => ['vjcrdf', 'москва'],
    'capitalized Latin' => ['Vjcrdf', 'Москва'],
    'punctuation keys' => ['[f,fhjdcr', 'хабаровск'],
    'shifted punctuation keys' => ['{f,fhjdcr', 'Хабаровск'],
    'apostrophe key' => ["'ktrnhjcnfkm", 'электросталь'],
    'backtick and semicolon keys' => ['`;br', 'ёжик'],
    'hyphens, spaces and digits are kept' => ['Cfyrn-Gtnth,ehu 2', 'Санкт-Петербург 2'],
    'mixed case' => ['Ghbvjhcrbq', 'Приморский'],
    'Cyrillic to Latin' => ['ьщысщц', 'moscow'],
    'Cyrillic letters on punctuation keys' => ['хабаровск', '[f,fhjdcr'],
    'capital Cyrillic' => ['Зфкшы', 'Paris'],
]);

test('text is not retyped when its letters are not from one layout', function (string $typed) {
    expect(KeyboardLayout::swap($typed))->toBeNull();
})->with([
    'mixed layouts' => ['vjcква'],
    'letters outside both layouts' => ['münchen'],
    'Ukrainian letters' => ['київ'],
    'no letters' => ['123 - 45'],
    'punctuation only' => ['[];'],
    'empty' => [''],
]);
