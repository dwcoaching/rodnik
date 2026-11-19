<?php

declare(strict_types=1);

use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function authPage(string $path, string $locale): HTMLDocument
{
    /** @var TestResponse $response */
    $response = test()->withHeader('X-Rodnik-Locale', $locale)->get($path)->assertOk();

    return HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
}

test('the login and registration pages name themselves and link to each other', function (string $locale, array $login, array $register) {
    $page = authPage('/login', $locale);
    $link = $page->querySelector('h1 ~ div a[href="'.route('register').'"]');

    expect(mb_trim($page->querySelector('h1')->textContent))->toBe($login['title'])
        ->and($page->querySelector('title')->textContent)->toBe($login['title'].' — Rodnik.today')
        ->and(mb_trim($page->querySelector('form[action="'.route('login').'"] button[type="submit"]')->textContent))->toBe($login['submit'])
        ->and(mb_trim($link?->textContent ?? ''))->toBe($login['link'])
        ->and($page->querySelector('a[href="'.route('password.request').'"]'))->not->toBeNull();

    $page = authPage('/register', $locale);
    $link = $page->querySelector('h1 ~ div a[href="'.route('login').'"]');

    expect(mb_trim($page->querySelector('h1')->textContent))->toBe($register['title'])
        ->and($page->querySelector('title')->textContent)->toBe($register['title'].' — Rodnik.today')
        ->and(mb_trim($page->querySelector('form[action="'.route('register').'"] button[type="submit"]')->textContent))->toBe($register['submit'])
        ->and(mb_trim($link?->textContent ?? ''))->toBe($register['link'])
        // One password field with a "show" toggle: nothing to repeat.
        ->and($page->querySelectorAll('input[type="password"]')->length)->toBe(1);

    expect(authPage('/forgot-password', $locale)->querySelector('h1 ~ div a[href="'.route('login').'"]'))->not->toBeNull();
})->with([
    'English' => [
        'en',
        ['title' => 'Log in', 'submit' => 'Log in', 'link' => 'Register'],
        ['title' => 'Create an account', 'submit' => 'Create account', 'link' => 'Log in'],
    ],
    'Russian' => [
        'ru',
        ['title' => 'Вход', 'submit' => 'Войти', 'link' => 'Зарегистрироваться'],
        ['title' => 'Регистрация', 'submit' => 'Зарегистрироваться', 'link' => 'Войти'],
    ],
]);

test('a failed login is reported once above the form and registration errors next to their fields', function () {
    $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password']);
    $page = authPage('/login', 'en');

    expect(mb_trim($page->querySelector('[role="alert"]')->textContent))->toBe(__('auth.failed', [], 'en'))
        ->and($page->querySelector('#email-error'))->toBeNull()
        ->and($page->querySelector('#email')->getAttribute('value'))->toBe('nobody@example.com');

    $this->from('/register')->post('/register', [
        'name' => 'Test User',
        'email' => 'new@example.com',
        'password' => 'short',
        'nickname' => null,
    ]);
    $page = authPage('/register', 'en');

    expect($page->querySelector('#password-error'))->not->toBeNull()
        ->and($page->querySelector('#password')->getAttribute('aria-invalid'))->toBe('true')
        ->and($page->querySelector('#password')->getAttribute('aria-describedby'))->toBe('password-error')
        ->and($page->querySelector('[role="alert"]'))->toBeNull()
        ->and($page->querySelector('#name')->getAttribute('value'))->toBe('Test User');
});

test('phones see only the login link in the header', function () {
    $page = authPage('/', 'en');
    $register = $page->querySelector('a[data-rodnik-return][href^="'.route('register').'"]');

    expect($register->classList->contains('hidden'))->toBeTrue()
        ->and($register->classList->contains('sm:block'))->toBeTrue()
        ->and($page->querySelector('a[data-rodnik-return][href^="'.route('login').'"]')->classList->contains('hidden'))->toBeFalse();
});
