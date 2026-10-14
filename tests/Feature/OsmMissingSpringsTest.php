<?php

declare(strict_types=1);

use App\Actions\Springs\MergeSpringsAction;
use App\Jobs\PruneMissingOSMSprings;
use App\Livewire\Duo\Reports\Index as ReportsIndex;
use App\Livewire\Duo\Springs\Show;
use App\Models\OverpassBatch;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function completeBatch(): OverpassBatch
{
    $batch = OverpassBatch::create([]);
    $batch->parse_status = 'parsed';
    $batch->coverage = 100;
    $batch->save();

    return $batch;
}

function goneFromOsm(array $attributes = []): Spring
{
    return Spring::factory()->create(array_merge([
        'osm_node_id' => fake()->unique()->numberBetween(1, 1_000_000),
        'osm_missing_since' => '2026-10-13 00:17:43',
        'latitude' => 55.0,
        'longitude' => 37.0,
    ], $attributes));
}

function reportOn(Spring $spring, ?User $user = null): Report
{
    return Report::factory()->create(['spring_id' => $spring->id, 'user_id' => $user?->id]);
}

test('a spring that left OSM with nothing on Rodnik referring to it is deleted', function () {
    $batch = completeBatch();
    $spring = goneFromOsm();

    (new PruneMissingOSMSprings($batch))->handle();

    expect(Spring::find($spring->id))->toBeNull();
});

test('a spring that left OSM is kept when it has reports, edits by people, merged duplicates or was hidden', function () {
    $batch = completeBatch();

    $reported = goneFromOsm();
    reportOn($reported);

    $edited = goneFromOsm();
    $revision = new SpringRevision();
    $revision->spring_id = $edited->id;
    $revision->revision_type = 'user';
    $revision->save();

    $mergeTarget = goneFromOsm();
    Spring::factory()->create(['redirect_to_spring_id' => $mergeTarget->id]);

    $hidden = goneFromOsm(['hidden_at' => now()]);

    (new PruneMissingOSMSprings($batch))->handle();

    expect(Spring::find($reported->id))->not->toBeNull()
        ->and(Spring::find($edited->id))->not->toBeNull()
        ->and(Spring::find($mergeTarget->id))->not->toBeNull()
        ->and(Spring::find($hidden->id))->not->toBeNull();
});

test('a spring that left OSM can be merged into another source, one still in OSM cannot', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $target = Spring::factory()->create(['latitude' => 55.001, 'longitude' => 37.0, 'osm_node_id' => 1]);

    $gone = goneFromOsm();
    app(MergeSpringsAction::class)($gone, $target->id);

    expect($gone->fresh()->redirect_to_spring_id)->toBe($target->id);

    $present = goneFromOsm(['osm_missing_since' => null]);

    expect(fn () => app(MergeSpringsAction::class)($present, $target->id))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

test('admins are offered to merge a spring that left OSM', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(Show::class, ['springId' => goneFromOsm()->id])
        ->assertSee('Merge into another…');

    Livewire::test(Show::class, ['springId' => goneFromOsm(['osm_missing_since' => null])->id])
        ->assertDontSee('Merge into another…');
});

test('a hidden spring that left OSM says why it is gone from the map', function () {
    $spring = goneFromOsm(['hidden_at' => now()]);

    Livewire::test(Show::class, ['springId' => $spring->id])
        ->assertSee('This object is hidden.')
        ->assertSee('It was deleted from OpenStreetMap or no longer matches our tags');

    $notOsm = Spring::factory()->create(['hidden_at' => now()]);

    Livewire::test(Show::class, ['springId' => $notOsm->id])
        ->assertSee("Probably it's not a water source");
});

test('the author keeps seeing reports on a spring hidden after they reported it', function () {
    $author = User::factory()->create(['is_admin' => false, 'is_superadmin' => false]);
    $spring = goneFromOsm(['hidden_at' => now(), 'name' => 'Vanished spring']);
    reportOn($spring, $author);

    $this->actingAs($author);
    Livewire::test(ReportsIndex::class, ['userId' => $author->id])
        ->assertSee('Vanished spring')
        ->assertSee('hidden');

    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(ReportsIndex::class, ['userId' => $author->id])
        ->assertSee('Vanished spring');

    $this->actingAs(User::factory()->create(['is_admin' => false, 'is_superadmin' => false]));
    Livewire::test(ReportsIndex::class, ['userId' => $author->id])
        ->assertDontSee('Vanished spring');
});

test('the author map layer keeps a spring hidden after they reported it', function () {
    $author = User::factory()->create();
    $spring = goneFromOsm(['hidden_at' => now()]);
    reportOn($spring, $author);

    $this->getJson("/users/{$author->id}/springs.json")
        ->assertOk()
        ->assertJsonPath('features.0.id', $spring->id);
});
