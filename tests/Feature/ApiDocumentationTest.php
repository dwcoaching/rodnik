<?php

declare(strict_types=1);

use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests see the api documentation menu item in both languages', function () {
    $this->get('/docs/about')
        ->assertOk()
        ->assertSee('href="/docs/api"', false);
    $this->get('/ru/docs/about')
        ->assertOk()
        ->assertSee('href="/ru/docs/api"', false);
});

test('documentation navigation exposes localized accessible menu controls and the current page', function (string $prefix, string $navigationLabel, string $closeLabel) {
    $response = $this->get($prefix.'/docs/about')->assertOk();
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $menuButton = $document->querySelector('header button[aria-controls]');
    $navigation = $document->getElementById($menuButton->getAttribute('aria-controls'));
    $currentPage = $navigation->querySelector('a[aria-current="page"]');
    $closeButtons = $navigation->querySelectorAll('button[aria-label="'.$closeLabel.'"]');

    expect($menuButton->getAttribute('type'))->toBe('button')
        ->and($menuButton->getAttribute('aria-label'))->toBe($navigationLabel)
        ->and($menuButton->getAttribute(':aria-expanded'))->toBe('navigationOpen')
        ->and($menuButton->getAttribute('@click'))->toBe('navigationOpen = true')
        ->and($navigation->getAttribute('aria-label'))->toBe($navigationLabel)
        ->and($navigation->getAttribute('x-show'))->toBe('navigationOpen || desktop')
        ->and($navigation->getAttribute('x-trap.inert.noscroll'))->toBe('navigationOpen && !desktop')
        ->and($navigation->getAttribute(':role'))->toBe("desktop ? null : 'dialog'")
        ->and($navigation->getAttribute(':aria-modal'))->toBe("desktop ? null : 'true'")
        ->and($navigation->parentElement->getAttribute('@keydown.escape.window'))->toBe('navigationOpen = false')
        ->and($navigation->querySelector('nav')->getAttribute('aria-label'))->toBe($navigationLabel)
        ->and($navigation->querySelectorAll('a[aria-current="page"]')->length)->toBe(1)
        ->and($currentPage->getAttribute('href'))->toBe($prefix.'/docs/about')
        ->and($closeButtons->length)->toBe(2);

    foreach ($closeButtons as $closeButton) {
        expect($closeButton->getAttribute('type'))->toBe('button')
            ->and($closeButton->getAttribute('@click'))->toBe('navigationOpen = false');
    }
})->with([
    'English' => ['', 'Navigation', 'Close'],
    'Russian' => ['/ru', 'Навигация', 'Закрыть'],
]);

test('the api documentation is public, indexable and lists every endpoint', function () {
    $this->get('/docs/api')
        ->assertOk()
        ->assertSee('Rodnik.today API v1')
        ->assertSee('/auth/register')
        ->assertSee('/auth/token')
        ->assertSee('/springs/{spring}')
        ->assertSee('/users/{user}/reports')
        ->assertSee('/reports/{report}/photos')
        ->assertSee('/photos/{photo}')
        ->assertSee('POST /springs')
        ->assertSee('Delete the account')
        ->assertSee('Rate limits')
        ->assertSee('Recommended save workflow')
        ->assertSee('Create the report before uploading photos')
        ->assertSee('Persist the returned report ID')
        ->assertSee('Upload photos sequentially in the desired display order')
        ->assertSee('Client state model')
        ->assertSee('Partial success and recovery')
        ->assertSee('do not retry it blindly')
        ->assertSee('Retry-After')
        ->assertSee('API client best practices')
        ->assertSee('<code>Water tap</code>', false)
        ->assertDontSee('/areas/')
        ->assertDontSee('water_confirmed')
        ->assertDontSee('api.')
        ->assertDontSee('noindex, nofollow', false);
});

test('the api documentation has a russian version with the same structure', function () {
    $english = $this->get('/docs/api')->assertOk();
    $russian = $this->get('/ru/docs/api')
        ->assertOk()
        ->assertSee('API Rodnik.today v1')
        ->assertSee('Регистрация')
        ->assertSee('Добавить источник')
        ->assertSee('Отчёты пользователя')
        ->assertSee('Удалить аккаунт')
        ->assertSee('/users/{user}/reports')
        ->assertDontSee('api.');

    $headings = fn ($response): array => collect(HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR)
        ->querySelectorAll('article h2[id], article h3[id]'))
        ->map(fn ($heading): string => $heading->getAttribute('id'))
        ->all();

    expect($headings($russian))->toBe($headings($english))->not->toBeEmpty();
});
