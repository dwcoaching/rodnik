<?php

declare(strict_types=1);

use App\Jobs\SendSpringRevisionNotification;
use App\Livewire\Duo\Springs\Create;
use App\Models\Spring;
use App\Models\SpringRevision;
use App\Models\SpringTile;
use App\Models\User;
use App\Models\WateredSpringTile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake([SendSpringRevisionNotification::class]);
    Storage::fake(SpringTile::DISK);
    Storage::fake(WateredSpringTile::DISK);
});

test('a signed-in user can add a source from the web form', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Create::class, ['springId' => null, 'location' => null])
        ->set('latitude', '40.18')
        ->set('longitude', '44.51')
        ->call('create')
        ->assertRedirect();

    expect(Spring::query()->sole()->latitude)->toEqual(40.18)
        ->and(SpringRevision::query()->sole()->user_id)->toBe($user->id);
});

test('the web form rejects missing coordinates with the usual message', function () {
    $component = Livewire::actingAs(User::factory()->create())
        ->test(Create::class, ['springId' => null, 'location' => null])
        ->call('create')
        ->assertHasErrors(['latitude', 'longitude']);

    expect($component->errors()->first('latitude'))->toBe(__('ui.validation.invalid_coordinates'));

    expect(Spring::query()->count())->toBe(0);
});

test('guests cannot add a source from the web form', function () {
    Livewire::test(Create::class, ['springId' => null, 'location' => null])
        ->set('latitude', '40.18')
        ->set('longitude', '44.51')
        ->call('create')
        ->assertForbidden();

    expect(Spring::query()->count())->toBe(0);
});
