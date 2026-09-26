<?php

declare(strict_types=1);

use App\Models\User;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests do not see the api documentation menu item', function () {
    $this->get('/docs/about')
        ->assertOk()
        ->assertDontSee(route('docs.api'));
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

test('guests are redirected away from the api documentation', function () {
    $this->get('/docs/api')->assertRedirect('/login');
});

test('any authenticated user can see the api documentation and its menu item', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get('/docs/about')
        ->assertOk()
        ->assertSee(route('docs.api'));

    $this->actingAs($user)
        ->get('/docs/api')
        ->assertOk()
        ->assertSee('Rodnik.today Mobile API v1')
        ->assertSee('POST')
        ->assertSee('/auth/token')
        ->assertSee('/springs/{spring}')
        ->assertSee('/reports/{report}/photos')
        ->assertSee('/photos/{photo}')
        ->assertSee('Recommended save workflow')
        ->assertSee('Create the report before uploading photos')
        ->assertSee('Persist the returned report ID')
        ->assertSee('Upload photos sequentially in the desired display order')
        ->assertSee('Client state model')
        ->assertSee('Partial success and recovery')
        ->assertSee('do not retry it blindly')
        ->assertSee('Retry-After')
        ->assertSee('API client best practices')
        ->assertDontSee('water_confirmed')
        ->assertSee('noindex, nofollow', false);
});
