<?php

declare(strict_types=1);

use App\Actions\Reports\TransferReportToSpringAction;
use App\Filament\Resources\ReportResource;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringTile;
use App\Models\User;
use App\Models\WateredSpringTile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function transferTestSpring(array $attributes = []): Spring
{
    return Spring::factory()->create(array_merge([
        'latitude' => 55.0000,
        'longitude' => 37.0000,
    ], $attributes));
}

function transferTestReport(Spring $spring, ?User $user = null): Report
{
    return Report::factory()->create([
        'spring_id' => $spring->id,
        'user_id' => $user?->id,
        'state' => 'running',
        'quality' => 'good',
    ]);
}

function transferTestGeneratedTilePathsForSpring(Spring $spring): array
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

function transferTestExpectTileFilesMissing(array $paths): void
{
    foreach ($paths as [$disk, $path]) {
        Storage::disk($disk)->assertMissing($path);
    }
}

test('admin can transfer a report to another visible water source', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = transferTestSpring();
    $target = transferTestSpring(['latitude' => 55.0010]);
    $reportUser = User::factory()->create(['cached_rating' => 42]);
    $report = transferTestReport($source, $reportUser);

    $transferred = app(TransferReportToSpringAction::class)($report, $target->id);

    expect($transferred->spring_id)->toBe($target->id);
    expect($report->fresh()->spring_id)->toBe($target->id);
    expect($reportUser->fresh()->cached_rating)->toBe(42);
});

test('report transfer invalidates source and target map tiles', function () {
    Storage::fake(SpringTile::DISK);
    Storage::fake(WateredSpringTile::DISK);
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = transferTestSpring();
    $target = transferTestSpring([
        'latitude' => 55.1000,
        'longitude' => 37.1000,
    ]);
    $report = transferTestReport($source);

    $sourcePaths = transferTestGeneratedTilePathsForSpring($source);
    $targetPaths = transferTestGeneratedTilePathsForSpring($target);

    app(TransferReportToSpringAction::class)($report, $target->id);

    transferTestExpectTileFilesMissing($sourcePaths);
    transferTestExpectTileFilesMissing($targetPaths);
});

test('report transfer rejects transferring to the same water source', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = transferTestSpring();
    $report = transferTestReport($source);

    expect(fn () => app(TransferReportToSpringAction::class)($report, $source->id))
        ->toThrow(ValidationException::class, 'already attached');

    expect($report->fresh()->spring_id)->toBe($source->id);
});

test('report transfer rejects a hidden target water source', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = transferTestSpring();
    $target = transferTestSpring(['hidden_at' => now()]);
    $report = transferTestReport($source);

    expect(fn () => app(TransferReportToSpringAction::class)($report, $target->id))
        ->toThrow(ValidationException::class, 'hidden');

    expect($report->fresh()->spring_id)->toBe($source->id);
});

test('report transfer rejects a redirected target water source', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = transferTestSpring();
    $finalTarget = transferTestSpring(['latitude' => 55.0020]);
    $redirectedTarget = transferTestSpring([
        'latitude' => 55.0010,
        'redirect_to_spring_id' => $finalTarget->id,
    ]);
    $report = transferTestReport($source);

    expect(fn () => app(TransferReportToSpringAction::class)($report, $redirectedTarget->id))
        ->toThrow(ValidationException::class, 'redirected');

    expect($report->fresh()->spring_id)->toBe($source->id);
});

test('report transfer rejects missing target water source', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = transferTestSpring();
    $report = transferTestReport($source);

    expect(fn () => app(TransferReportToSpringAction::class)($report, 999999))
        ->toThrow(ValidationException::class, 'does not exist');

    expect($report->fresh()->spring_id)->toBe($source->id);
});

test('report transfer requires admin access', function () {
    $source = transferTestSpring();
    $target = transferTestSpring(['latitude' => 55.0010]);
    $report = transferTestReport($source);

    $this->actingAs(User::factory()->create([
        'is_admin' => false,
        'is_superadmin' => false,
    ]));

    expect(fn () => app(TransferReportToSpringAction::class)($report, $target->id))
        ->toThrow(AuthorizationException::class);

    expect(ReportResource::canAccess())->toBeFalse();
});

test('report resource distance preview marks transfers over one hundred meters in red', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $source = transferTestSpring();
    $target = transferTestSpring(['latitude' => 55.0010]);
    $report = transferTestReport($source);
    $method = new ReflectionMethod(ReportResource::class, 'distancePreview');

    $preview = (string) $method->invoke(null, $report, $target->id);

    expect(ReportResource::canAccess())->toBeTrue();
    expect($preview)->toContain('111 m');
    expect($preview)->toContain('text-danger-600');
});
