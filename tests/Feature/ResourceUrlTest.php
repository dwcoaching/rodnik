<?php

declare(strict_types=1);

use App\Livewire\Duo;
use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use App\Support\DuoUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('resource URLs render the source directly with localized canonical metadata', function (string $prefix, string $locale) {
    $spring = Spring::factory()->create(['name' => 'A source & a view', 'type' => 'Spring']);
    $path = $prefix.'/'.$spring->id.'/';

    $this->get($path)
        ->assertSuccessful()
        ->assertViewHas('page', ['spring' => $spring->id, 'user' => null, 'location' => null])
        ->assertSee('<html lang="'.$locale.'">', false)
        ->assertSee('<title>A source &amp; a view — Rodnik.today</title>', false)
        ->assertSee('<link rel="canonical" href="'.url('/').$path.'">', false)
        ->assertSee('hreflang="en" href="'.url('/').'/'.$spring->id.'/"', false)
        ->assertSee('hreflang="ru" href="'.url('/').'/ru/'.$spring->id.'/"', false)
        ->assertDontSee('name="robots" content="noindex', false);
})->with([
    'English' => ['', 'en'],
    'Russian' => ['/ru', 'ru'],
]);

test('all historical source URL forms redirect permanently to the final resource URL', function (string $pattern, string $prefix) {
    $target = Spring::factory()->create();
    $middle = Spring::factory()->create(['redirect_to_spring_id' => $target->id]);
    $source = Spring::factory()->create(['redirect_to_spring_id' => $middle->id]);
    $path = str_replace('{id}', (string) $source->id, $pattern);

    $this->get($path)
        ->assertStatus(301)
        ->assertRedirect(url('/').$prefix.'/'.$target->id.'/');
})->with([
    'short query' => ['/?s={id}', ''],
    'original Livewire query' => ['/?spring_id={id}', ''],
    'nested query' => ['/?page[spring]={id}', ''],
    'historical view query' => ['/?view[spring]={id}', ''],
    'flat query' => ['/?spring={id}', ''],
    'short path' => ['/{id}', ''],
    'numeric with slash' => ['/{id}/', ''],
    'resource path' => ['/springs/{id}', ''],
    'resource path with slash' => ['/springs/{id}/', ''],
    'English prefix' => ['/en/{id}/', ''],
    'English prefixed query' => ['/en?page[spring]={id}', ''],
    'English prefixed resource' => ['/en/springs/{id}', ''],
    'Russian short query' => ['/ru?s={id}', '/ru'],
    'Russian original Livewire query' => ['/ru?spring_id={id}', '/ru'],
    'Russian nested query' => ['/ru?page[spring]={id}', '/ru'],
    'Russian historical view query' => ['/ru?view[spring]={id}', '/ru'],
    'Russian short path' => ['/ru/{id}', '/ru'],
    'Russian path with slash' => ['/ru/{id}/', '/ru'],
    'Russian resource path' => ['/ru/springs/{id}', '/ru'],
]);

test('unmerged old paths gain their slash and legacy context stays encoded', function () {
    $spring = Spring::factory()->create();
    $user = User::factory()->create();
    $query = http_build_query([
        's' => $spring->id,
        'u' => $user->id,
        'track' => 'an & encoded # track + name',
        'filters' => ['type' => ['spring', 'water well']],
    ], '', '&', PHP_QUERY_RFC3986);

    $response = $this->get('/ru?'.$query)->assertStatus(301);
    $target = $response->headers->get('Location');
    parse_str(parse_url($target, PHP_URL_QUERY), $restoredQuery);

    expect(parse_url($target, PHP_URL_PATH))->toBe('/ru/'.$spring->id.'/')
        ->and($restoredQuery)->toBe([
            'track' => 'an & encoded # track + name',
            'filters' => ['type' => ['spring', 'water well']],
            'user' => (string) $user->id,
        ]);

    $this->get('/'.$spring->id)
        ->assertStatus(301)
        ->assertRedirect(url('/').'/'.$spring->id.'/');
});

test('source identity determines canonical while user and arbitrary view context stay shareable', function () {
    $spring = Spring::factory()->create();
    $user = User::factory()->create();
    $context = '?user='.$user->id.'&track=a%26b%23c%2Bd&utm_source=test&unrelated=1';

    $this->get('/ru/'.$spring->id.'/'.$context)
        ->assertSuccessful()
        ->assertViewHas('page', ['spring' => $spring->id, 'user' => $user->id, 'location' => null])
        ->assertSee('<link rel="canonical" href="'.url('/').'/ru/'.$spring->id.'/">', false)
        ->assertSee('hreflang="en" href="'.url('/').'/'.$spring->id.'/"', false);
});

test('user URLs are independent resources and historical user queries redirect permanently', function (string $pattern, string $prefix) {
    $user = User::factory()->create();
    $path = str_replace('{id}', (string) $user->id, $pattern);
    $canonical = url('/').$prefix.'/users/'.$user->id.'/';

    $this->get($path)->assertStatus(301)->assertRedirect($canonical);
    $this->get($prefix.'/users/'.$user->id.'/')
        ->assertSuccessful()
        ->assertViewHas('page', ['spring' => null, 'user' => $user->id, 'location' => null])
        ->assertSee('<link rel="canonical" href="'.$canonical.'">', false);
})->with([
    'short query' => ['/?u={id}', ''],
    'nested query' => ['/?page[user]={id}', ''],
    'old path' => ['/users/{id}', ''],
    'Russian query' => ['/ru?page[user]={id}', '/ru'],
    'Russian path' => ['/ru/users/{id}', '/ru'],
    'English prefixed path' => ['/en/users/{id}/', ''],
]);

test('missing or malformed source and user identities are real not found responses', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    '/999999999/',
    '/999999999999999999999999999/',
    '/0/',
    '/users/0/',
    '/ru/999999999/',
    '/users/999999999/',
    '/?page[spring]=not-an-id',
    '/?s[]=1',
    '/?u=invalid',
]);

test('hidden sources and merged-source inspection are noindex and keep clean canonicals', function () {
    $target = Spring::factory()->create();
    $hidden = Spring::factory()->create(['hidden_at' => now()]);
    $source = Spring::factory()->create(['redirect_to_spring_id' => $target->id]);

    foreach (['/'.$hidden->id.'/', '/'.$source->id.'/?redirect=false'] as $path) {
        $this->get($path)
            ->assertSuccessful()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('<link rel="alternate"', false);
    }

    $this->get('/'.$source->id.'/?redirect=false')
        ->assertSuccessful()
        ->assertSee('<link rel="canonical" href="'.url('/').'/'.$source->id.'/">', false);
});

test('location workflows remain addressable and excluded from indexing', function () {
    $spring = Spring::factory()->create();

    $this->get('/springs/'.$spring->id.'/location/edit')
        ->assertStatus(301)
        ->assertRedirect(url('/').'/'.$spring->id.'/?location=1');

    $this->get('/ru?page[spring]='.$spring->id.'&page[location]=1')
        ->assertStatus(301)
        ->assertRedirect(url('/').'/ru/'.$spring->id.'/?location=1');

    $this->get('/ru/'.$spring->id.'/?location=1')
        ->assertSuccessful()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee('<link rel="canonical" href="'.url('/').'/ru/'.$spring->id.'/">', false);

    $this->get('/springs/create')->assertStatus(301)->assertRedirect(url('/').'/?location=1');
    $this->get('/?locating=1')->assertStatus(301)->assertRedirect(url('/').'/?location=1');
    $this->get('/?view[location]=1')->assertStatus(301)->assertRedirect(url('/').'/?location=1');
    $this->get('/?location=1')->assertSuccessful()->assertSee('name="robots" content="noindex', false);
});

test('historical edit and history paths preserve their meaning', function () {
    $spring = Spring::factory()->create();

    foreach (['edit', 'history'] as $section) {
        $this->get('/ru/springs/'.$spring->id.'/'.$section)
            ->assertStatus(301)
            ->assertRedirect('/ru/'.$spring->id.'/'.$section);
    }
});

test('legacy location links resolve merged targets directly and retain user context', function () {
    $target = Spring::factory()->create();
    $source = Spring::factory()->create(['redirect_to_spring_id' => $target->id]);
    $user = User::factory()->create();

    $this->get('/'.$source->id.'/location/edit?u='.$user->id)
        ->assertStatus(301)
        ->assertRedirect(url('/').'/'.$target->id.'/?user='.$user->id.'&location=1');
});

test('resource link generation accepts models and preserves query values', function () {
    $spring = Spring::factory()->create();
    $user = User::factory()->create();
    App::setLocale('ru');

    expect(duo_route(['spring' => $spring, 'user' => $user, 'track' => 'a&b']))
        ->toBe(url('/').'/ru/'.$spring->id.'/?track=a%26b&user='.$user->id)
        ->and(localized_route('springs.show', ['springId' => $spring->id]))
        ->toBe(url('/').'/ru/'.$spring->id.'/')
        ->and(localized_route('springs.show', ['springId' => $spring->id, 'user' => $user->id, 'track' => 'a&b']))
        ->toBe(url('/').'/ru/'.$spring->id.'/?track=a%26b&user='.$user->id)
        ->and(app(DuoUrl::class)->relative(['user' => $user->id], 'en'))
        ->toBe('/users/'.$user->id.'/');
});

test('source links in a user contribution list preserve its map context', function () {
    $user = User::factory()->create();
    $spring = Spring::factory()->create();
    Report::factory()->create(['user_id' => $user->id, 'spring_id' => $spring->id]);

    $this->get('/users/'.$user->id.'/')
        ->assertSuccessful()
        ->assertSee('href="'.url('/').'/'.$spring->id.'/?user='.$user->id.'"', false);
});

test('Duo accepts server resource state without advertising reactive URL history', function () {
    $spring = Spring::factory()->create();

    Livewire::test(Duo::class, ['page' => ['spring' => $spring->id]])
        ->assertSet('page', ['spring' => $spring->id, 'user' => null, 'location' => null])
        ->assertSee($spring->name);
});
