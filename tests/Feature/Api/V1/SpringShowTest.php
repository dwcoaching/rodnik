<?php

declare(strict_types=1);

use App\Models\Photo;
use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a public spring response nests visible reports and ordered photo resources', function () {
    $spring = Spring::factory()->create([
        'latitude' => '55.755800',
        'longitude' => '37.617300',
        'intermittent' => 'no',
    ]);
    $user = User::factory()->create(['name' => 'Report Author']);

    $older = Report::factory()->create([
        'spring_id' => $spring->id,
        'user_id' => null,
        'visited_at' => '2026-08-10',
        'state' => 'dry',
        'quality' => null,
    ]);
    $newer = Report::factory()->create([
        'spring_id' => $spring->id,
        'user_id' => $user->id,
        'visited_at' => '2026-08-15',
        'state' => 'running',
        'quality' => 'good',
        'access_limited' => null,
    ]);
    $secondPhoto = Photo::factory()->create(['report_id' => $newer->id, 'order' => 2]);
    $firstPhoto = Photo::factory()->create(['report_id' => $newer->id, 'order' => 1]);

    Report::factory()->create(['spring_id' => $spring->id, 'hidden_at' => now()]);
    Report::factory()->create(['spring_id' => $spring->id, 'from_osm' => true]);

    $response = $this->getJson("/api/v1/springs/{$spring->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $spring->id)
        ->assertJsonPath('data.latitude', 55.7558)
        ->assertJsonPath('data.longitude', 37.6173)
        ->assertJsonPath('data.reports_count', 2)
        ->assertJsonPath('data.reports.0.id', $newer->id)
        ->assertJsonPath('data.reports.0.author.id', $user->id)
        ->assertJsonPath('data.reports.0.photos.0.id', $firstPhoto->id)
        ->assertJsonPath('data.reports.0.photos.1.id', $secondPhoto->id)
        ->assertJsonPath('data.reports.1.id', $older->id)
        ->assertJsonPath('data.reports.1.author', null)
        ->assertJsonCount(2, 'data.reports');
});

test('hidden springs are not exposed', function () {
    $spring = Spring::factory()->create(['hidden_at' => now()]);

    $this->getJson("/api/v1/springs/{$spring->id}")->assertNotFound();
});

test('merged springs permanently redirect to their visible canonical target', function () {
    $target = Spring::factory()->create();
    $source = Spring::factory()->create(['redirect_to_spring_id' => $target->id]);

    $this->getJson("/api/v1/springs/{$source->id}")
        ->assertStatus(308)
        ->assertRedirect(route('api.v1.springs.show', ['spring' => $target]));
});
