<?php

declare(strict_types=1);

use App\Models\Spring;
use App\Models\User;
use App\Support\LocalizedUrl;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Jetstream;

uses(RefreshDatabase::class);

function signIn(User $user): Illuminate\Testing\TestResponse
{
    return test()->post('/login', ['email' => $user->email, 'password' => 'password']);
}

test('guest login and registration links on a new report carry the report page back', function (string $prefix) {
    $spring = Spring::factory()->create();
    $path = $prefix.'/reports/create?spring_id='.$spring->id;

    $document = HTMLDocument::createFromString($this->get($path)->assertOk()->getContent(), LIBXML_NOERROR);
    $expected = ['login' => route('login', ['redirect' => $path]), 'register' => route('register', ['redirect' => $path])];

    foreach ($expected as $href) {
        expect($document->querySelectorAll('a[data-rodnik-return][href="'.$href.'"]')->length)->toBeGreaterThanOrEqual(1);
    }
})->with(['English' => '', 'Russian' => '/ru']);

test('auth pages do not point their own login links back at themselves', function () {
    $document = HTMLDocument::createFromString($this->get('/login?redirect=/ru')->assertOk()->getContent(), LIBXML_NOERROR);

    expect($document->querySelector('a[data-rodnik-return][href="'.route('login').'"]'))->not->toBeNull();
});

test('signing in from a new report returns to it in the language of the page', function () {
    $spring = Spring::factory()->create();
    $user = User::factory()->create(['locale' => null]);
    $path = '/ru/reports/create?spring_id='.$spring->id;

    $this->get('/login?redirect='.urlencode($path))->assertOk()->assertSessionHas('url.intended', url($path));

    signIn($user)
        ->assertRedirect($path)
        ->assertCookie(config('localization.cookie'), 'ru');

    expect($user->refresh()->locale)->toBe('ru');
});

test('the saved profile language wins over the guest page language', function () {
    $spring = Spring::factory()->create();
    $user = User::factory()->create(['locale' => 'ru']);

    $this->get('/login?redirect='.urlencode('/reports/create?spring_id='.$spring->id));

    signIn($user)
        ->assertRedirect('/ru/reports/create?spring_id='.$spring->id)
        ->assertCookie(config('localization.cookie'), 'ru');

    expect($user->refresh()->locale)->toBe('ru');
});

test('a language picked during this visit wins over the saved profile language and replaces it', function () {
    $spring = Spring::factory()->create();
    $user = User::factory()->create(['locale' => 'en']);

    $this->post('/locale/ru', ['redirect' => '/ru/reports/create?spring_id='.$spring->id])->assertRedirect();
    $this->get('/login?redirect='.urlencode('/ru/reports/create?spring_id='.$spring->id));

    signIn($user)->assertRedirect('/ru/reports/create?spring_id='.$spring->id);

    expect($user->refresh()->locale)->toBe('ru');
    $this->assertFalse(session()->has('locale.chosen'));
});

test('signing in without a return page lands on the map in the profile language', function (?string $locale, string $expected) {
    $user = User::factory()->create(['locale' => $locale]);

    signIn($user)->assertRedirect($expected);
})->with([
    'Russian profile' => ['ru', '/ru'],
    'English profile' => ['en', '/'],
    'no saved language' => [null, '/'],
]);

test('return pages are rewritten into the chosen language and keep their query and fragment', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->get('/login?redirect='.urlencode('/ru/12345?filter=watered#map=10/55.7/37.6'));

    signIn($user)->assertRedirect('/12345?filter=watered#map=10/55.7/37.6');
});

test('unsafe or auth return pages are ignored', function (string $redirect) {
    $user = User::factory()->create(['locale' => 'ru']);

    $this->get('/login?redirect='.urlencode($redirect))->assertOk()->assertSessionMissing('url.intended');

    signIn($user)->assertRedirect('/ru');
})->with([
    'protocol relative' => ['//evil.example/x'],
    'absolute' => ['https://evil.example/x'],
    'backslash' => ['/\\evil.example'],
    'login itself' => ['/login'],
    'registration' => ['/register?redirect=/ru'],
    'livewire endpoint' => ['/livewire/update'],
]);

test('an intended page set by the auth middleware is still honoured and localized', function () {
    $user = User::factory()->create(['locale' => 'ru']);

    $this->get('/user/maps')->assertRedirect(route('login'));

    signIn($user)->assertRedirect('/ru/user/maps');
});

test('registering from a new report returns to it and saves the page language', function () {
    $spring = Spring::factory()->create();
    $path = '/ru/reports/create?spring_id='.$spring->id;

    $this->get('/register?redirect='.urlencode($path))->assertOk();

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'new-reporter@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        'nickname' => null,
    ])->assertRedirect($path);

    expect(User::query()->where('email', 'new-reporter@example.com')->value('locale'))->toBe('ru');
});

test('the report pages show the language switcher', function (string $prefix, string $target) {
    $spring = Spring::factory()->create();

    $document = HTMLDocument::createFromString(
        $this->get($prefix.'/reports/create?spring_id='.$spring->id)->assertOk()->getContent(),
        LIBXML_NOERROR,
    );

    expect($document->querySelector('form[data-rodnik-locale="'.$target.'"] input[name="redirect"]')?->getAttribute('value'))
        ->toBe(($target === 'ru' ? '/ru' : '').'/reports/create?spring_id='.$spring->id);
})->with([
    'English page offers Russian' => ['', 'ru'],
    'Russian page offers English' => ['/ru', 'en'],
]);

test('local paths are localized only when they have localized versions', function (string $path, string $locale, string $expected, ?string $pathLocale) {
    $urls = app(LocalizedUrl::class);

    expect($urls->forPath($path, $locale))->toBe($expected)
        ->and($urls->localeOfPath($path))->toBe($pathLocale);
})->with([
    'map root to Russian' => ['/', 'ru', '/ru', 'en'],
    'Russian root to English' => ['/ru', 'en', '/', 'ru'],
    'spring keeps query and fragment' => ['/12345?x=1#h', 'ru', '/ru/12345?x=1#h', 'en'],
    'saved map keeps trailing slash' => ['/ru/maps/coast-walk/', 'en', '/maps/coast-walk/', 'ru'],
    'docs page' => ['/docs/about', 'ru', '/ru/docs/about', 'en'],
    'login has no localized version' => ['/login', 'ru', '/login', null],
    'unknown path stays' => ['/no-such-page', 'ru', '/no-such-page', null],
]);
