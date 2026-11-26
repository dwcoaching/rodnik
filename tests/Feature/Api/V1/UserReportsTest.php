<?php

declare(strict_types=1);

use App\Models\Photo;
use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('a user report list is public, newest first, and includes the source and photos', function () {
    $user = User::factory()->create(['name' => 'Report Author']);
    $spring = Spring::factory()->create(['name' => 'Forest spring', 'type' => 'Spring', 'latitude' => '55.755800', 'longitude' => '37.617300']);
    $older = Report::factory()->create(['user_id' => $user->id, 'spring_id' => $spring->id, 'created_at' => now()->subDay()]);
    $newer = Report::factory()->create(['user_id' => $user->id, 'spring_id' => $spring->id, 'created_at' => now()]);
    $photo = Photo::factory()->create(['report_id' => $newer->id]);

    Report::factory()->create(['user_id' => $user->id, 'hidden_at' => now()]);
    Report::factory()->create(['user_id' => $user->id, 'from_osm' => true]);
    Report::factory()->create();

    $this->getJson("/api/v1/users/{$user->id}/reports")
        ->assertOk()
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.author.name', 'Report Author')
        ->assertJsonPath('data.0.spring', [
            'id' => $spring->id,
            'name' => 'Forest spring',
            'type' => 'Spring',
            'latitude' => 55.7558,
            'longitude' => 37.6173,
        ])
        ->assertJsonPath('data.0.photos.0.id', $photo->id)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 50);
});

test('the spring source block only appears in the report list', function () {
    $spring = Spring::factory()->create();
    Report::factory()->create(['spring_id' => $spring->id]);

    $this->getJson("/api/v1/springs/{$spring->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.reports.0.spring');
});

test('the report list is paginated by fifty with stable ordering for equal timestamps', function () {
    $user = User::factory()->create();
    $reports = Report::factory()->count(51)->create(['user_id' => $user->id, 'created_at' => now()]);

    $first = $this->getJson("/api/v1/users/{$user->id}/reports")->assertOk()->assertJsonCount(50, 'data');
    $second = $this->getJson("/api/v1/users/{$user->id}/reports?page=2")->assertOk()->assertJsonCount(1, 'data');

    expect($first->json('meta.last_page'))->toBe(2)
        ->and($first->json('data.0.id'))->toBe($reports->max('id'))
        ->and($second->json('data.0.id'))->toBe($reports->min('id'));
});

test('reports on hidden sources are shown only to their author and admins', function () {
    $user = User::factory()->create();
    $hiddenSpring = Spring::factory()->create(['hidden_at' => now()]);
    Report::factory()->create(['user_id' => $user->id, 'spring_id' => $hiddenSpring->id]);

    $this->getJson("/api/v1/users/{$user->id}/reports")->assertOk()->assertJsonCount(0, 'data');

    Sanctum::actingAs(User::factory()->create(), ['*']);
    $this->getJson("/api/v1/users/{$user->id}/reports")->assertOk()->assertJsonCount(0, 'data');

    Sanctum::actingAs($user, ['*']);
    $this->getJson("/api/v1/users/{$user->id}/reports")->assertOk()->assertJsonCount(1, 'data');

    Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['*']);
    $this->getJson("/api/v1/users/{$user->id}/reports")->assertOk()->assertJsonCount(1, 'data');
});

test('an unknown user returns 404', function () {
    $this->getJson('/api/v1/users/999999/reports')->assertNotFound();
});
