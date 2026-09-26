<?php

declare(strict_types=1);

use App\Livewire\Duo;
use App\Models\Map;
use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use Dom\HTMLDocument;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('Duo navigation renders selected resources while keeping its parent component mounted', function () {
    $first = Spring::factory()->create(['name' => 'First source']);
    $second = Spring::factory()->create(['name' => 'Second source', 'longitude' => 37.6, 'latitude' => 55.7]);
    $user = User::factory()->create(['name' => 'Water walker']);
    Report::factory()->create(['spring_id' => $first->id, 'user_id' => $user->id]);
    $component = Livewire::test(Duo::class, ['page' => ['spring' => $first->id]]);
    $parentId = $component->snapshot['memo']['id'];
    $firstChild = array_values($component->snapshot['memo']['children'])[0][1];

    $component->call('navigateTo', '/'.$second->id.'/?user='.$user->id.'&utm_source=test#map=4/1/2')
        ->assertSet('page', ['spring' => $second->id, 'user' => $user->id, 'location' => null])
        ->assertSee('Second source')
        ->assertDontSee('First source')
        ->assertNoRedirect()
        ->assertReturned(fn (array $result): bool => $result['page'] === ['spring' => $second->id, 'user' => $user->id, 'location' => null]
            && $result['coordinates'] === [37.6, 55.7]
            && $result['url'] === '/'.$second->id.'/?utm_source=test&user='.$user->id
            && $result['metadata']['canonical'] === url('/').'/'.$second->id.'/');

    expect($component->snapshot['memo']['id'])->toBe($parentId)
        ->and(array_values($component->snapshot['memo']['children'])[0][1])->not->toBe($firstChild);

    $component->call('navigateTo', '/users/'.$user->id.'/')
        ->assertSet('page', ['spring' => null, 'user' => $user->id, 'location' => null])
        ->assertSee('Water walker')
        ->assertNoRedirect()
        ->assertReturned(fn (array $result): bool => $result['coordinates'] === []
            && $result['url'] === '/users/'.$user->id.'/');

    $component->call('navigateTo', '/')
        ->assertSet('page', ['spring' => null, 'user' => null, 'location' => null])
        ->assertNoRedirect();

    expect($component->snapshot['memo']['id'])->toBe($parentId);
});

test('Duo resolves merged sources and legacy resource context like a direct request', function () {
    $target = Spring::factory()->create(['name' => 'Final source']);
    $middle = Spring::factory()->create(['redirect_to_spring_id' => $target->id]);
    $source = Spring::factory()->create(['redirect_to_spring_id' => $middle->id]);
    $user = User::factory()->create();

    Livewire::test(Duo::class)
        ->call('navigateTo', '/springs/'.$source->id.'/location/edit?u='.$user->id.'&context=a%26b')
        ->assertSet('page', ['spring' => $target->id, 'user' => $user->id, 'location' => 1])
        ->assertNoRedirect()
        ->assertReturned(fn (array $result): bool => $result['url'] === '/'.$target->id.'/?context=a%26b&user='.$user->id.'&location=1'
            && $result['metadata']['robots'] === 'noindex, nofollow')
        ->call('navigateTo', '/'.$source->id.'/?redirect=false')
        ->assertSet('page.spring', $source->id)
        ->assertReturned(fn (array $result): bool => $result['url'] === '/'.$source->id.'/?redirect=false'
            && $result['metadata']['robots'] === 'noindex, nofollow');
});

test('location navigation remounts coordinates for each selected source and for a new source', function () {
    $first = Spring::factory()->create(['latitude' => 55.7, 'longitude' => 37.6]);
    $second = Spring::factory()->create(['latitude' => 40.7, 'longitude' => -74.0]);
    $component = Livewire::test(Duo::class);

    $component->call('navigateTo', '/'.$first->id.'/?location=1')
        ->assertSee(__('ui.spring.update_location'))
        ->assertSee('55.7')
        ->assertNoRedirect();
    $firstChild = array_values($component->snapshot['memo']['children'])[0][1];

    $component->call('navigateTo', '/'.$second->id.'/?location=1')
        ->assertSee('40.7')
        ->assertDontSee('55.7')
        ->assertNoRedirect();
    expect(array_values($component->snapshot['memo']['children'])[0][1])->not->toBe($firstChild);

    $component->call('navigateTo', '/?location=1')
        ->assertSet('page', ['spring' => null, 'user' => null, 'location' => 1])
        ->assertSee(__('ui.spring.new_water_source'))
        ->assertNoRedirect();
});

test('component navigation metadata matches server rendering in each locale', function (string $locale, string $prefix, bool $hidden) {
    $spring = Spring::factory()->create(['name' => 'A source & a view', 'hidden_at' => $hidden ? now() : null]);
    $path = $prefix.'/'.$spring->id.'/?utm_source=test';
    $response = $this->get($path)->assertSuccessful();
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    App::setLocale($locale);
    $component = Livewire::test(Duo::class)->call('navigateTo', $path)->assertNoRedirect();
    $metadata = $component->effects['returns'][0]['metadata'];

    expect($metadata['title'])->toBe($document->querySelector('title')->textContent)
        ->and($metadata['description'])->toBe($document->querySelector('meta[name="description"]')->getAttribute('content'))
        ->and($metadata['canonical'])->toBe($document->querySelector('link[rel="canonical"]')->getAttribute('href'))
        ->and($metadata['robots'])->toBe($document->querySelector('meta[name="robots"]')?->getAttribute('content'))
        ->and($metadata['locale'])->toBe($locale);

    foreach ($metadata['alternates'] as $language => $url) {
        expect($document->querySelector('link[hreflang="'.$language.'"]')->getAttribute('href'))->toBe($url);
    }
})->with([
    'English public source' => ['en', '', false],
    'Russian public source' => ['ru', '/ru', false],
    'English hidden source' => ['en', '', true],
    'Russian hidden source' => ['ru', '/ru', true],
]);

test('leaving a shared map clears the component shared identity', function () {
    $state = Map::factory()->make()->state;

    Livewire::test(Duo::class, ['sharedState' => $state])
        ->assertSet('isSharedMap', true)
        ->call('navigateTo', '/')
        ->assertSet('isSharedMap', false)
        ->assertNoRedirect();
});

test('component navigation rejects missing identities and non Duo destinations', function (string $href) {
    Livewire::test(Duo::class)->call('navigateTo', $href)->assertNotFound();
})->with([
    '/0/',
    '/999999999999999999999999999/',
    '/?user[]=1',
    '/?spring=invalid',
    '/docs/about',
    '/user/maps',
    '/share/Missing1',
    '/ru/',
    'https://example.com/1/',
    '//example.com/1/',
    '/\\example.com/1/',
]);

test('component navigation rejects resources that no longer exist', function (string $href) {
    Livewire::test(Duo::class)->call('navigateTo', $href);
})->with(['spring' => '/999999999/', 'user' => '/users/999999999/'])->throws(ModelNotFoundException::class);

test('Duo page identities cannot bypass navigation validation by updating properties', function () {
    Livewire::test(Duo::class)->set('page.spring', 123);
})->throws(CannotUpdateLockedPropertyException::class);

test('navigation metadata uses plain values for untrusted source titles', function () {
    $spring = Spring::factory()->create(['name' => '<script>alert("source")</script>']);

    Livewire::test(Duo::class)
        ->call('navigateTo', '/'.$spring->id.'/')
        ->assertSee('&lt;script&gt;alert', false)
        ->assertDontSee('<script>alert("source")</script>', false)
        ->assertReturned(fn (array $result): bool => $result['metadata']['title'] === '<script>alert("source")</script> — Rodnik.today');
});

test('navigating to the current resource reloads server content without replacing Duo', function (string $resource) {
    $spring = Spring::factory()->create(['name' => 'Earlier source name', 'latitude' => 55.7, 'longitude' => 37.6]);
    $user = User::factory()->create(['name' => 'Earlier contributor name']);
    $page = match ($resource) {
        'user' => ['user' => $user->id],
        'location' => ['spring' => $spring->id, 'location' => 1],
        default => ['spring' => $spring->id],
    };
    $href = match ($resource) {
        'user' => '/users/'.$user->id.'/',
        'location' => '/'.$spring->id.'/?location=1',
        default => '/'.$spring->id.'/',
    };
    $component = Livewire::test(Duo::class, ['page' => $page]);
    $parentId = $component->snapshot['memo']['id'];
    $childId = array_values($component->snapshot['memo']['children'])[0][1];

    $spring->name = 'Updated source name';
    $spring->latitude = 40.7;
    $spring->save();
    $user->name = 'Updated contributor name';
    $user->save();

    $component->call('navigateTo', $href)->assertNoRedirect();

    expect($component->snapshot['memo']['id'])->toBe($parentId)
        ->and(array_values($component->snapshot['memo']['children'])[0][1])->not->toBe($childId);

    if ($resource === 'location') {
        $document = HTMLDocument::createFromString($component->html(false), LIBXML_NOERROR);
        $location = $document->querySelector('[wire\:name="duo.springs.create"]');
        $snapshot = json_decode($location->getAttribute('wire:snapshot'), true, flags: JSON_THROW_ON_ERROR);

        expect((float) $snapshot['data']['latitude'])->toBe(40.7);
    } else {
        $component->assertSee($resource === 'user' ? 'Updated contributor name' : 'Updated source name')
            ->assertDontSee($resource === 'user' ? 'Earlier contributor name' : 'Earlier source name');
    }
})->with(['source', 'user', 'location']);
