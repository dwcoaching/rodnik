<?php

use App\Models\User;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringTile;
use App\Models\WateredSpringTile;
use Livewire\Livewire;
use App\Livewire\Duo;
use App\Library\StatisticsService;
use App\Actions\Springs\MergeSpringsAction;
use App\Actions\Springs\UnmergeSpringsAction;
use App\Actions\Reports\MoveReportToSpringAction;
use App\Livewire\Duo\Springs\MergeModal;
use App\Livewire\Duo\Springs\Show as SpringShow;
use App\Livewire\Reports\Show as ReportShow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createMergeableSpring(array $attributes = []): Spring
{
    return Spring::factory()->create(array_merge([
        'latitude' => 55.0000,
        'longitude' => 37.0000,
    ], $attributes));
}

function createSpringReport(Spring $spring, ?User $user = null): Report
{
    $report = new Report();
    $report->spring_id = $spring->id;
    $report->user_id = $user?->id;
    $report->state = 'running';
    $report->quality = 'good';
    $report->comment = 'Fresh water';
    $report->created_at = now();
    $report->updated_at = now();
    $report->save();

    return $report;
}

function renderedReportCountForSpring(Spring $spring): int
{
    $component = new SpringShow();
    $component->springId = $spring->id;
    $component->userId = null;

    return $component->render()->getData()['reports']->count();
}

function putGeneratedTileFilesForSpring(Spring $spring): array
{
    $paths = [];

    foreach (SpringTile::fromCoordinates($spring->longitude, $spring->latitude) as $tile) {
        Storage::disk(SpringTile::DISK)->put($tile->path(), '{}');
        $tile->generated_at = now();
        $tile->save();
        $paths[] = [SpringTile::DISK, $tile->path()];
    }

    foreach (WateredSpringTile::fromCoordinates($spring->longitude, $spring->latitude) as $tile) {
        Storage::disk(WateredSpringTile::DISK)->put($tile->path(), '{}');
        $tile->generated_at = now();
        $tile->save();
        $paths[] = [WateredSpringTile::DISK, $tile->path()];
    }

    return $paths;
}

function expectTileFilesMissing(array $paths): void
{
    foreach ($paths as [$disk, $path]) {
        Storage::disk($disk)->assertMissing($path);
    }
}

test('merge target may already redirect and source resolves to the final target', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = createMergeableSpring();
    $finalTarget = createMergeableSpring(['latitude' => 55.0005]);
    $redirectedTarget = createMergeableSpring([
        'latitude' => 55.0010,
        'redirect_to_spring_id' => $finalTarget->id,
    ]);

    app(MergeSpringsAction::class)($source, $redirectedTarget->id);

    $source->refresh();
    expect($source->redirect_to_spring_id)->toBe($redirectedTarget->id);
    expect($source->finallyRedirectedTo()->id)->toBe($finalTarget->id);
});

test('merge cannot create a redirect loop', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = createMergeableSpring(['latitude' => 55.0010]);
    $middle = createMergeableSpring(['latitude' => 55.0005]);
    $target = createMergeableSpring();
    $target->redirect_to_spring_id = $middle->id;
    $target->save();
    $middle->redirect_to_spring_id = $source->id;
    $middle->save();

    expect(fn () => app(MergeSpringsAction::class)($source, $target->id))
        ->toThrow(ValidationException::class);

    expect($source->fresh()->redirect_to_spring_id)->toBeNull();
});

test('final redirect target resolution throws on an existing redirect loop', function () {
    $first = createMergeableSpring();
    $second = createMergeableSpring(['latitude' => 55.0005]);
    $first->redirect_to_spring_id = $second->id;
    $first->save();
    $second->redirect_to_spring_id = $first->id;
    $second->save();

    expect(fn () => $first->finallyRedirectedTo())->toThrow(\RuntimeException::class);
});

test('public spring count excludes redirected duplicates', function () {
    Cache::flush();

    $canonical = createMergeableSpring();
    createMergeableSpring(['latitude' => 55.0010]);
    createMergeableSpring([
        'latitude' => 55.0020,
        'redirect_to_spring_id' => $canonical->id,
    ]);
    createMergeableSpring([
        'latitude' => 55.0030,
        'hidden_at' => now(),
    ]);

    expect(StatisticsService::getSpringsCount())->toBe(2);
});

test('redirected spring initial page load redirects to final target', function () {
    $source = createMergeableSpring();
    $middle = createMergeableSpring(['latitude' => 55.0005]);
    $finalTarget = createMergeableSpring(['latitude' => 55.0010]);
    $source->redirect_to_spring_id = $middle->id;
    $source->save();
    $middle->redirect_to_spring_id = $finalTarget->id;
    $middle->save();

    Livewire::withQueryParams(['page' => ['spring' => $source->id]])
        ->test(Duo::class)
        ->assertRedirect(duo_route(['spring' => $finalTarget->id]));
});

test('redirected spring initial page load skips redirect with redirect false', function () {
    $source = createMergeableSpring();
    $target = createMergeableSpring(['latitude' => 55.0010]);
    $source->redirect_to_spring_id = $target->id;
    $source->save();

    Livewire::withQueryParams([
        'page' => ['spring' => $source->id],
        'redirect' => 'false',
    ])
        ->test(Duo::class)
        ->assertNoRedirect();
});

test('merge redirects back to the source page with redirect bypassed', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = createMergeableSpring();
    $target = createMergeableSpring(['latitude' => 55.0010]);

    Livewire::test(MergeModal::class)
        ->set('springId', $source->id)
        ->set('targetSpringId', $target->id)
        ->call('merge')
        ->assertRedirect(duo_route(['spring' => $source->id]) . '&redirect=false');

    expect($source->fresh()->redirect_to_spring_id)->toBe($target->id);
});

test('merge requires admin or superadmin', function () {
    $source = createMergeableSpring();
    $target = createMergeableSpring(['latitude' => 55.0010]);

    $this->actingAs(User::factory()->create([
        'is_admin' => false,
        'is_superadmin' => false,
    ]));

    expect(fn () => app(MergeSpringsAction::class)($source, $target->id))
        ->toThrow(AuthorizationException::class);

    $this->actingAs(User::factory()->create(['is_admin' => true]));

    app(MergeSpringsAction::class)($source, $target->id);
    expect($source->fresh()->redirect_to_spring_id)->toBe($target->id);

    $superadminSource = createMergeableSpring(['latitude' => 55.0020]);
    $superadminTarget = createMergeableSpring(['latitude' => 55.0030]);

    $this->actingAs(User::factory()->create([
        'is_admin' => false,
        'is_superadmin' => true,
    ]));

    app(MergeSpringsAction::class)($superadminSource, $superadminTarget->id);
    expect($superadminSource->fresh()->redirect_to_spring_id)->toBe($superadminTarget->id);
});

test('merge action rejects OSM-tracked source spring', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = createMergeableSpring(['osm_node_id' => 12345]);
    $target = createMergeableSpring(['latitude' => 55.0010]);

    expect(fn () => app(MergeSpringsAction::class)($source, $target->id))
        ->toThrow(ValidationException::class);

    expect($source->fresh()->redirect_to_spring_id)->toBeNull();
});

test('merge action rejects hidden target spring', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = createMergeableSpring();
    $target = createMergeableSpring([
        'latitude' => 55.0010,
        'hidden_at' => now(),
    ]);

    expect(fn () => app(MergeSpringsAction::class)($source, $target->id))
        ->toThrow(ValidationException::class);

    expect($source->fresh()->redirect_to_spring_id)->toBeNull();
});

test('merge action rejects target outside merge radius', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = createMergeableSpring();
    $target = createMergeableSpring(['latitude' => 56.0000]);

    expect(fn () => app(MergeSpringsAction::class)($source, $target->id))
        ->toThrow(ValidationException::class);

    expect($source->fresh()->redirect_to_spring_id)->toBeNull();
});

test('merge modal candidate list excludes already redirected targets', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = createMergeableSpring();
    $candidate = createMergeableSpring(['latitude' => 55.0010]);
    $finalTarget = createMergeableSpring(['latitude' => 55.0015]);
    $redirectedCandidate = createMergeableSpring([
        'latitude' => 55.0020,
        'redirect_to_spring_id' => $finalTarget->id,
    ]);

    Livewire::test(MergeModal::class)
        ->set('springId', $source->id)
        ->set('open', true)
        ->assertSee('#' . $candidate->id)
        ->assertDontSee('#' . $redirectedCandidate->id);
});

test('merge modal candidate list excludes hidden targets', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = createMergeableSpring();
    $candidate = createMergeableSpring(['latitude' => 55.0010]);
    $hiddenCandidate = createMergeableSpring([
        'latitude' => 55.0020,
        'hidden_at' => now(),
    ]);

    Livewire::test(MergeModal::class)
        ->set('springId', $source->id)
        ->set('open', true)
        ->assertSee('#' . $candidate->id)
        ->assertDontSee('#' . $hiddenCandidate->id);
});

test('unmerge clears only the selected redirect link', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = createMergeableSpring();
    $target = createMergeableSpring(['latitude' => 55.0010]);
    $otherSource = createMergeableSpring(['latitude' => 55.0020]);
    $source->redirect_to_spring_id = $target->id;
    $source->save();
    $otherSource->redirect_to_spring_id = $target->id;
    $otherSource->save();

    app(UnmergeSpringsAction::class)($source);

    expect($source->fresh()->redirect_to_spring_id)->toBeNull();
    expect($otherSource->fresh()->redirect_to_spring_id)->toBe($target->id);
});

test('unmerge in the middle of a chain splits the chain correctly', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $first = createMergeableSpring();
    $middle = createMergeableSpring(['latitude' => 55.0005]);
    $finalTarget = createMergeableSpring(['latitude' => 55.0010]);
    $first->redirect_to_spring_id = $middle->id;
    $first->save();
    $middle->redirect_to_spring_id = $finalTarget->id;
    $middle->save();

    app(UnmergeSpringsAction::class)($middle);

    expect($first->fresh()->finallyRedirectedTo()->id)->toBe($middle->id);
    expect($middle->fresh()->redirect_to_spring_id)->toBeNull();
});

test('report can be moved to merge target and restored', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = createMergeableSpring();
    $target = createMergeableSpring(['latitude' => 55.0010]);
    $source->redirect_to_spring_id = $target->id;
    $source->save();

    $reportUser = User::factory()->create(['cached_rating' => 99]);
    $report = createSpringReport($source, $reportUser);
    $action = app(MoveReportToSpringAction::class);

    $moved = $action($report, $target->id);
    expect($moved->spring_id)->toBe($target->id);
    expect($reportUser->fresh()->cached_rating)->toBe(1);

    $restored = $action($moved, $source->id);
    expect($restored->spring_id)->toBe($source->id);
});

test('moved report invalidates both source and target tiles', function () {
    Storage::fake(SpringTile::DISK);
    Storage::fake(WateredSpringTile::DISK);
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = createMergeableSpring();
    $target = createMergeableSpring([
        'latitude' => 55.1000,
        'longitude' => 37.1000,
    ]);
    $source->redirect_to_spring_id = $target->id;
    $source->save();
    $report = createSpringReport($source, User::factory()->create());

    $sourcePaths = putGeneratedTileFilesForSpring($source);
    $targetPaths = putGeneratedTileFilesForSpring($target);

    app(MoveReportToSpringAction::class)($report, $target->id);

    expectTileFilesMissing($sourcePaths);
    expectTileFilesMissing($targetPaths);
});

test('moved report cannot move to hidden target spring', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = createMergeableSpring();
    $target = createMergeableSpring([
        'latitude' => 55.0010,
        'hidden_at' => now(),
    ]);
    $report = createSpringReport($source, User::factory()->create());

    expect(fn () => app(MoveReportToSpringAction::class)($report, $target->id))
        ->toThrow(ValidationException::class);

    expect($report->fresh()->spring_id)->toBe($source->id);
});

test('moved report action requires admin', function () {
    $source = createMergeableSpring();
    $target = createMergeableSpring(['latitude' => 55.0010]);
    $report = createSpringReport($source, User::factory()->create());

    $this->actingAs(User::factory()->create([
        'is_admin' => false,
        'is_superadmin' => false,
    ]));

    expect(fn () => app(MoveReportToSpringAction::class)($report, $target->id))
        ->toThrow(AuthorizationException::class);
});

test('merged spring report menu can move a report and show restore confirmation', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $source = createMergeableSpring();
    $target = createMergeableSpring(['latitude' => 55.0010]);
    $source->redirect_to_spring_id = $target->id;
    $source->save();

    $report = createSpringReport($source, User::factory()->create());

    Livewire::test(ReportShow::class, ['report' => $report])
        ->assertSee('Move to #' . $target->id)
        ->call('moveToRedirectTarget')
        ->assertSee('Report moved to #' . $target->id)
        ->assertSee('Undo')
        ->call('undoMoveToRedirectTarget')
        ->assertSee('Move to #' . $target->id);

    expect($report->fresh()->spring_id)->toBe($source->id);
});

test('reports count on source page updates after move and after undo', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = createMergeableSpring();
    $target = createMergeableSpring(['latitude' => 55.0010]);
    $source->redirect_to_spring_id = $target->id;
    $source->save();

    $report = createSpringReport($source, User::factory()->create());
    createSpringReport($source, User::factory()->create());

    expect(renderedReportCountForSpring($source))->toBe(2);

    $component = Livewire::test(ReportShow::class, ['report' => $report])
        ->call('moveToRedirectTarget');

    expect(renderedReportCountForSpring($source))->toBe(1);

    $component->call('undoMoveToRedirectTarget');

    expect(renderedReportCountForSpring($source))->toBe(2);
});

test('reports count on target page updates after move', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = createMergeableSpring();
    $target = createMergeableSpring(['latitude' => 55.0010]);
    $source->redirect_to_spring_id = $target->id;
    $source->save();

    $report = createSpringReport($source, User::factory()->create());
    createSpringReport($target, User::factory()->create());

    expect(renderedReportCountForSpring($target))->toBe(1);

    Livewire::test(ReportShow::class, ['report' => $report])
        ->call('moveToRedirectTarget');

    expect(renderedReportCountForSpring($target))->toBe(2);
});
