<?php

declare(strict_types=1);

use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

uses(RefreshDatabase::class);

test('user ranking counts visible reports and unique existing sources in stable report order', function () {
    $earlierUser = User::factory()->create(['name' => 'Earlier contributor']);
    $leader = User::factory()->create(['name' => 'Leading contributor', 'cached_rating' => 0]);
    $laterUser = User::factory()->create(['name' => 'Later contributor']);
    $inactiveUser = User::factory()->create(['name' => 'No contributions']);
    $excludedUser = User::factory()->create(['name' => 'Excluded contributions']);
    $spring = Spring::factory()->create();
    $hiddenSpring = Spring::factory()->create(['hidden_at' => now()]);
    $deletedSpring = Spring::factory()->create();

    Report::factory()->count(3)->for($leader)->for($spring)->create();
    Report::factory()->for($leader)->for($hiddenSpring)->create();
    Report::factory()->count(2)->for($earlierUser)->for($spring)->create();
    Report::factory()->count(2)->for($laterUser)->create();

    foreach ([$leader, $excludedUser] as $user) {
        Report::factory()->for($user)->create(['hidden_at' => now()]);
        Report::factory()->for($user)->create(['from_osm' => true]);
        Report::factory()->for($user)->for($deletedSpring)->create();
    }

    $deletedSpring->delete();

    $this->get('/docs/users')
        ->assertSuccessful()
        ->assertSeeInOrder([$leader->name, $earlierUser->name, $laterUser->name])
        ->assertDontSee($inactiveUser->name)
        ->assertDontSee($excludedUser->name)
        ->assertViewHas('users', function (LengthAwarePaginator $users) use ($leader, $earlierUser, $laterUser): bool {
            expect($users->total())->toBe(3)
                ->and($users->getCollection()->modelKeys())->toBe([$leader->id, $earlierUser->id, $laterUser->id])
                ->and($users->getCollection()->pluck('reports_count')->map(fn (mixed $count): int => (int) $count)->all())->toBe([4, 2, 2])
                ->and($users->getCollection()->pluck('springs_count')->map(fn (mixed $count): int => (int) $count)->all())->toBe([2, 1, 2]);

            return true;
        });
});

test('user ranking is localized and links contributors to their map', function (string $locale, string $prefix, string $routeName, string $title, array $columns) {
    $user = User::factory()->create(['name' => 'Map contributor']);
    Report::factory()->for($user)->create();

    $this->get($prefix.'/docs/users')
        ->assertSuccessful()
        ->assertSee('<html lang="'.$locale.'">', false)
        ->assertSee($title)
        ->assertSee($columns)
        ->assertSee('href="'.e(route($locale === 'ru' ? 'ru.duo' : 'duo').'?page[user]='.$user->id).'"', false)
        ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->getCollection()->modelKeys() === [$user->id]);

    expect(route($routeName, absolute: false))->toBe($prefix.'/docs/users');

    $about = $this->get($prefix.'/docs/about')->assertSuccessful();
    $document = HTMLDocument::createFromString($about->getContent());
    $links = $document->querySelectorAll('a[href="'.url($prefix.'/docs/users').'"]');

    expect($links->length)->toBeGreaterThanOrEqual(2);
})->with([
    'English' => ['en', '', 'docs.users', 'Spring explorers', ['User', 'Reports', 'Unique water sources']],
    'Russian' => ['ru', '/ru', 'ru.docs.users', 'Родникологи', ['Пользователь', 'Отчёты', 'Уникальные источники']],
]);

test('user ranking shows a localized empty state', function (string $path, string $message) {
    User::factory()->create();

    $this->get($path)
        ->assertSuccessful()
        ->assertSee($message)
        ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->isEmpty() && $users->total() === 0);
})->with([
    'English' => ['/docs/users', 'No reports yet.'],
    'Russian' => ['/ru/docs/users', 'Пока нет отчётов.'],
]);

test('user ranking paginates without resetting contributor ranks', function () {
    $spring = Spring::factory()->create();
    $contributors = User::factory()->count(101)
        ->has(Report::factory()->for($spring))
        ->create();

    $this->get('/docs/users')
        ->assertSuccessful()
        ->assertViewHas('users', function (LengthAwarePaginator $users) use ($contributors): bool {
            expect($users->total())->toBe(101)
                ->and($users->perPage())->toBe(100)
                ->and($users->getCollection()->modelKeys())->toBe($contributors->take(100)->modelKeys());

            return true;
        });

    $response = $this->get('/docs/users?page=2')
        ->assertSuccessful()
        ->assertSee($contributors->last()->name)
        ->assertDontSee($contributors->first()->name)
        ->assertViewHas('users', function (LengthAwarePaginator $users) use ($contributors): bool {
            expect($users->currentPage())->toBe(2)
                ->and($users->firstItem())->toBe(101)
                ->and($users->getCollection()->modelKeys())->toBe([$contributors->last()->id]);

            return true;
        });

    $document = HTMLDocument::createFromString($response->getContent());
    $rank = $document->querySelector('tbody tr')?->querySelector('th, td');

    expect(mb_trim($rank?->textContent ?? ''))->toBe('101');
});
