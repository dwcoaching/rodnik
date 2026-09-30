<?php

declare(strict_types=1);

use App\Models\Report;
use App\Models\Spring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    config(['cache.default' => 'array']);
});

test('full export replaces previous exports only after the new files are written', function () {
    Report::factory()->for(Spring::factory())->create();

    $disk = Storage::disk('public');
    $disk->put('exports/rodnik-from-2026-09-29_22-30-00.json', 'old');
    $disk->put('exports/rodnik-from-2026-09-29_22-30-00.zip', 'old');
    $disk->put('exports/rodnik-from-2026-09-29_22-30-00.xlsx', 'old');
    $disk->put('exports/.partial-rodnik-from-2026-09-29_22-30-00.xlsx', 'leftover');
    $disk->put('exports/users/rodnik-user-1-from-2026-09-29_22-30-00.json', 'personal');

    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(22, 30));

    $this->artisan('export:full')->assertSuccessful();

    expect($disk->files('exports'))->toEqualCanonicalizing([
        'exports/rodnik-from-2026-09-30_22-30-00.json',
        'exports/rodnik-from-2026-09-30_22-30-00.zip',
        'exports/rodnik-from-2026-09-30_22-30-00.xlsx',
    ]);
    expect($disk->exists('exports/users/rodnik-user-1-from-2026-09-29_22-30-00.json'))->toBeTrue();

    $this->get('/docs/exports/xlsx/latest')
        ->assertRedirect('/exports/rodnik-from-2026-09-30_22-30-00.xlsx');
});
