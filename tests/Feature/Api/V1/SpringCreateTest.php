<?php

declare(strict_types=1);

use App\Jobs\SendSpringRevisionNotification;
use App\Models\Spring;
use App\Models\SpringRevision;
use App\Models\SpringTile;
use App\Models\User;
use App\Models\WateredSpringTile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake([SendSpringRevisionNotification::class]);
    Storage::fake(SpringTile::DISK);
    Storage::fake(WateredSpringTile::DISK);
});

test('creating a source requires authentication', function () {
    $this->postJson('/api/v1/springs', ['latitude' => 40.18, 'longitude' => 44.51])->assertUnauthorized();
});

test('a signed-in user can create a source with a name and type in one request', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/v1/springs', [
        'latitude' => 40.18,
        'longitude' => 44.51,
        'name' => 'Village tap',
        'type' => 'Water tap',
    ]);

    $spring = Spring::query()->sole();
    $response->assertCreated()
        ->assertJsonPath('data.id', $spring->id)
        ->assertJsonPath('data.name', 'Village tap')
        ->assertJsonPath('data.type', 'Water tap')
        ->assertJsonPath('data.latitude', 40.18)
        ->assertJsonPath('data.longitude', 44.51);

    $revision = SpringRevision::query()->sole();
    expect($revision->user_id)->toBe($user->id)
        ->and($revision->spring_id)->toBe($spring->id)
        ->and($revision->new_name)->toBe('Village tap')
        ->and($revision->new_type)->toBe('Water tap');
    Queue::assertPushed(SendSpringRevisionNotification::class);
});

test('name and type are optional', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson('/api/v1/springs', ['latitude' => 40.18, 'longitude' => 44.51])
        ->assertCreated()
        ->assertJsonPath('data.name', null)
        ->assertJsonPath('data.type', null);
});

test('coordinates and type are validated', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson('/api/v1/springs', ['latitude' => 'north', 'longitude' => 200, 'type' => 'Lake'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude', 'longitude', 'type']);

    $this->postJson('/api/v1/springs', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude', 'longitude']);

    expect(Spring::query()->count())->toBe(0);
});

test('a user can create at most thirty sources per hour', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    foreach (range(1, 30) as $index) {
        $this->postJson('/api/v1/springs', ['latitude' => 40 + $index / 100, 'longitude' => 44.51])->assertCreated();
    }

    $this->postJson('/api/v1/springs', ['latitude' => 41, 'longitude' => 44.51])->assertTooManyRequests();
});
