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

test('the language switcher is a dropdown naming the current language and offering both', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('aria-haspopup="listbox"', false)
        ->assertSee('<span>EN</span>', false)
        ->assertSee('action="'.route('locale.update', ['locale' => 'en']).'"', false)
        ->assertSee('action="'.route('locale.update', ['locale' => 'ru']).'"', false)
        ->assertSee('English')
        ->assertSee('Русский');

    $this->get('/ru')
        ->assertSuccessful()
        ->assertSee('<span>RU</span>', false)
        ->assertDontSee('<span>EN</span>', false);
});

test('the language switcher returns to the current page with its query string intact', function () {
    $spring = Spring::factory()->create();

    $this->get('/?page[spring]='.$spring->id.'&redirect=false')
        ->assertSuccessful()
        ->assertSee('value="/ru?page[spring]='.$spring->id.'&amp;redirect=false"', false);

    $this->get('/ru/docs/about?utm_source=newsletter')
        ->assertSuccessful()
        ->assertSee('value="/docs/about?utm_source=newsletter"', false);
});

test('locale forms rebuild the query string from the browser on submit', function () {
    $localeForm = file_get_contents(__DIR__.'/../../resources/views/components/locale-form.blade.php');

    expect($localeForm)
        ->toContain('x-ref="localeRedirect"')
        ->toContain('window.location.search')
        ->toContain('window.location.hash');

    // Every locale switch has to go through the component, or it goes stale
    // the moment Livewire rewrites the URL client-side.
    foreach (['language-switcher', 'language-suggestion'] as $component) {
        expect(file_get_contents(__DIR__.'/../../resources/views/components/'.$component.'.blade.php'))
            ->toContain('<x-locale-form')
            ->not->toContain('name="redirect"');
    }
});

test('the language switcher marks only the active locale as selected', function () {
    $response = $this->get('/ru')->assertSuccessful();

    $switcher = mb_substr(
        $response->getContent(),
        (int) mb_strpos($response->getContent(), 'aria-haspopup="listbox"'),
        4000,
    );

    expect($switcher)
        ->toContain('aria-selected="false"')
        ->toContain('English')
        ->toContain('aria-selected="true"')
        ->and(mb_substr_count($switcher, 'aria-selected="true"'))->toBe(1);
});

test('the navbar keeps the language switcher on the same row as the guest links', function () {
    $navbar = file_get_contents(__DIR__.'/../../resources/views/components/navbar.blade.php');

    expect($navbar)
        ->toContain('flex justify-between items-center flex-nowrap pt-4 pb-2')
        ->and(mb_substr_count($navbar, 'pt-4 pb-2'))->toBe(1);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('<a href="'.route('login').'" class="block text-sm text-gray-500">', false)
        ->assertSee('<a href="'.route('register').'" class="block text-sm text-gray-500">', false);
});

test('the navbar shows the language switcher next to the avatar for signed in users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/user/profile')
        ->assertSuccessful()
        ->assertSee('aria-haspopup="listbox"', false)
        ->assertSee('<span>EN</span>', false)
        ->assertSee('class="h-7 w-7 rounded-full object-cover"', false);
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

test('the map legend has a localized standalone page linked from About', function (string $locale, string $prefix, string $label) {
    $this->get($prefix.'/docs/legend')
        ->assertSuccessful()
        ->assertSee('<html lang="'.$locale.'">', false)
        ->assertSee('id="map-legend"', false)
        ->assertSee($label)
        ->assertSee('<link rel="canonical" href="'.url($prefix.'/docs/legend').'">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/docs/legend').'">', false)
        ->assertSee('<link rel="alternate" hreflang="ru" href="'.url('/ru/docs/legend').'">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/docs/legend').'">', false)
        ->assertDontSee('name="robots" content="noindex, nofollow"', false);

    $this->get($prefix.'/docs/about')
        ->assertSuccessful()
        ->assertSee('href="'.$prefix.'/docs/legend"', false)
        ->assertDontSee('id="map-legend"', false);
})->with([
    'English' => ['en', '', 'No user reports'],
    'Russian' => ['ru', '/ru', 'Нет отчётов пользователей'],
]);

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
        ->assertSee('<loc>'.url('/docs/legend').'</loc>', false)
        ->assertSee('<loc>'.url('/ru/docs/legend').'</loc>', false)
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
