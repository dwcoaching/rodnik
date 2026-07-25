<?php

declare(strict_types=1);

use App\Enums\ReportQuality;
use App\Enums\ReportState;
use App\Models\Spring;
use App\Models\User;
use App\Rules\LatitudeRule;
use App\Rules\LongitudeRule;
use App\Rules\SpringTypeRule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the English and Russian public roots render deterministic locales', function () {
    $this->withHeader('Accept-Language', 'ru-RU,ru;q=0.9,en;q=0.5')
        ->get('/')
        ->assertSuccessful()
        ->assertSee('<html lang="en">', false)
        ->assertSee('Map of public water sources with user reports in cities and wild places')
        ->assertDontSee('Карта общедоступных источников воды с отчётами пользователей в городах и на природе');

    $this->withHeader('Accept-Language', 'ru-RU,ru;q=0.9,en;q=0.5')
        ->get('/ru')
        ->assertSuccessful()
        ->assertSee('<html lang="ru">', false)
        ->assertSee('Карта общедоступных источников воды с отчётами пользователей в городах и на природе')
        ->assertDontSee('Map of public water sources with user reports in cities and wild places')
        ->assertDontSee('Russian version is available.');
});

test('existing English route names and paths remain compatible while Russian routes are prefixed', function () {
    expect(route('duo', absolute: false))->toBe('/')
        ->and(route('springs.create', absolute: false))->toBe('/create')
        ->and(route('springs.history', ['spring' => 42], false))->toBe('/42/history')
        ->and(route('ru.duo', absolute: false))->toBe('/ru')
        ->and(route('ru.springs.create', absolute: false))->toBe('/ru/create')
        ->and(route('ru.springs.history', ['spring' => 42], false))->toBe('/ru/42/history');

    App::setLocale('ru');

    expect(localized_route_name('duo'))->toBe('ru.duo')
        ->and(localized_route('duo', absolute: false))->toBe('/ru')
        ->and(duo_route(['spring' => 42]))->toBe(url('/ru').'?page[spring]=42');
});

test('indexable localized pages publish canonical and alternate language URLs', function () {
    $this->get('/ru')
        ->assertSuccessful()
        ->assertSee('<link rel="canonical" href="'.url('/ru').'">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/').'">', false)
        ->assertSee('<link rel="alternate" hreflang="ru" href="'.url('/ru').'">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/').'">', false)
        ->assertDontSee('name="robots" content="noindex, nofollow"', false);
});

test('map location state is not indexable and tracking parameters are removed from canonical URLs', function () {
    $this->get('/ru?page[location]=1&utm_source=locale-test&gclid=tracking')
        ->assertSuccessful()
        ->assertSee('<link rel="canonical" href="'.url('/ru?page[location]=1').'">', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertDontSee('<link rel="alternate"', false);
});

test('redirect bypass state is noindex and omitted from canonical URLs', function () {
    $spring = Spring::factory()->create();

    $this->get('/ru?page[spring]='.$spring->id.'&redirect=false&utm_source=locale-test')
        ->assertSuccessful()
        ->assertSee('<link rel="canonical" href="'.url('/ru?page[spring]='.$spring->id).'">', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertDontSee('<link rel="alternate"', false);
});

test('Accept-Language suggests Russian on English pages without redirecting', function () {
    $this->withHeader('Accept-Language', 'ru-RU,ru;q=0.9,en;q=0.5')
        ->get('/')
        ->assertSuccessful()
        ->assertSee('<html lang="en">', false)
        ->assertSee('Russian version is available.')
        ->assertSee('Open in Russian')
        ->assertSee('value="/ru"', false);
});

test('a guest can store a locale cookie and safely return to the equivalent page', function () {
    $this->post('/locale/ru', [
        'redirect' => '/ru/docs/about?from=language-picker',
    ])
        ->assertRedirect('/ru/docs/about?from=language-picker')
        ->assertCookie(config('localization.cookie'), 'ru');
});

test('locale switching rejects unsafe redirects and unsupported locales', function (string $locale, string $redirect, string $field) {
    $this->from('/')
        ->post('/locale/'.$locale, ['redirect' => $redirect])
        ->assertRedirect('/')
        ->assertSessionHasErrors($field)
        ->assertCookieMissing(config('localization.cookie'));
})->with([
    'absolute external URL' => ['ru', 'https://example.net/steal', 'redirect'],
    'protocol-relative URL' => ['ru', '//example.net/steal', 'redirect'],
    'backslash external URL' => ['ru', '/\\example.net/steal', 'redirect'],
    'unsupported locale' => ['de', '/', 'locale'],
]);

test('an authenticated locale switch persists the user preference', function () {
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)
        ->post('/locale/ru', ['redirect' => '/ru'])
        ->assertRedirect('/ru')
        ->assertCookie(config('localization.cookie'), 'ru');

    expect($user->fresh()->locale)->toBe('ru')
        ->and($user->fresh()->preferredLocale())->toBe('ru');

    $this->actingAs($user->fresh())
        ->get('/user/profile')
        ->assertSuccessful()
        ->assertSee('<html lang="ru">', false);
});

test('an explicit locale header localizes an unprefixed web route', function () {
    $this->withHeader('X-Rodnik-Locale', 'ru')
        ->get('/login')
        ->assertSuccessful()
        ->assertSee('<html lang="ru">', false)
        ->assertSee('Войти');
});

test('Folio pages have stable English and Russian URLs and content', function () {
    $this->get('/docs/about')
        ->assertSuccessful()
        ->assertSee('<html lang="en">', false)
        ->assertSee('Rodnik.today is a social layer on top of OpenStreetMap for exploring and sharing information about public water sources')
        ->assertSee('<link rel="canonical" href="'.url('/docs/about').'">', false)
        ->assertSee('<link rel="alternate" hreflang="ru" href="'.url('/ru/docs/about').'">', false);

    $this->get('/ru/docs/about')
        ->assertSuccessful()
        ->assertSee('<html lang="ru">', false)
        ->assertSee('Rodnik.today — социальный слой поверх OpenStreetMap для поиска общественных источников воды и обмена информацией о них')
        ->assertSee('<link rel="canonical" href="'.url('/ru/docs/about').'">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/docs/about').'">', false);
});

test('report enums and custom rules use the active locale', function () {
    App::setLocale('en');

    expect(ReportState::Running->getLabel())->toBe('Has water')
        ->and(ReportState::Dry->formLabel())->toBe('No water')
        ->and(ReportQuality::Good->getLabel())->toBe('Good water')
        ->and((new LatitudeRule)->message())->toBe('Invalid coordinates')
        ->and((new LongitudeRule)->message())->toBe('Invalid coordinates')
        ->and((new SpringTypeRule)->message())->toBe('Please select a water source type');

    App::setLocale('ru');

    expect(ReportState::Running->getLabel())->toBe('Вода есть')
        ->and(ReportState::Dry->formLabel())->toBe('Воды нет')
        ->and(ReportState::NotFound->gpxLabel())->toBe('Не найден')
        ->and(ReportQuality::Good->getLabel())->toBe('Хорошая вода')
        ->and((new LatitudeRule)->message())->toBe('Указаны недопустимые координаты')
        ->and((new LongitudeRule)->message())->toBe('Указаны недопустимые координаты')
        ->and((new SpringTypeRule)->message())->toBe('Выберите тип источника воды');
});

test('Livewire hydration keeps Carbon aligned with the snapshot locale', function () {
    App::setLocale('ru');
    Carbon::setLocale('en');

    Livewire::test(\App\Livewire\Duo::class)->call('$refresh');

    expect(Carbon::getLocale())->toBe('ru');
});

test('the sitemap index and static sitemap include both locales', function () {
    Spring::factory()->create();

    $this->get('/sitemap.xml')
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('sitemap.static'), false)
        ->assertSee(route('sitemap.springs', ['page' => 1]), false);

    $this->get('/sitemaps/static.xml')
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.url('/').'</loc>', false)
        ->assertSee('<loc>'.url('/ru').'</loc>', false)
        ->assertSee('hreflang="en" href="'.url('/docs/about').'"', false)
        ->assertSee('hreflang="ru" href="'.url('/ru/docs/about').'"', false)
        ->assertSee('hreflang="x-default" href="'.url('/docs/about').'"', false);
});

test('spring sitemaps include only visible canonical springs in both locales', function () {
    $visibleSpring = Spring::factory()->create();
    $hiddenSpring = Spring::factory()->create(['hidden_at' => now()]);
    $redirectedSpring = Spring::factory()->create(['redirect_to_spring_id' => $visibleSpring->id]);

    $englishUrl = route('duo', ['page' => ['spring' => $visibleSpring->id]]);
    $russianUrl = route('ru.duo', ['page' => ['spring' => $visibleSpring->id]]);

    $this->get('/sitemaps/springs-1.xml')
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.$englishUrl.'</loc>', false)
        ->assertSee('<loc>'.$russianUrl.'</loc>', false)
        ->assertSee('hreflang="en" href="'.$englishUrl.'"', false)
        ->assertSee('hreflang="ru" href="'.$russianUrl.'"', false)
        ->assertDontSee('page%5Bspring%5D='.$hiddenSpring->id, false)
        ->assertDontSee('page%5Bspring%5D='.$redirectedSpring->id, false);

    $this->get('/sitemaps/springs-2.xml')->assertNotFound();
});
