<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('latest export links redirect to the most recent file of each format', function (string $format, string $extension) {
    Storage::disk('public')->put("exports/rodnik-from-2026-09-28_22-30-00.{$extension}", 'old');
    Storage::disk('public')->put("exports/rodnik-from-2026-09-29_22-30-00.{$extension}", 'new');
    Storage::disk('public')->put('exports/rodnik-from-2026-09-30_22-30-00.other', 'other format');
    Storage::disk('public')->put("exports/users/rodnik-user-1-from-2026-09-30_22-30-00.{$extension}", 'personal');

    $this->get("/docs/exports/{$format}/latest")
        ->assertRedirect("/exports/rodnik-from-2026-09-29_22-30-00.{$extension}")
        ->assertHeader('Cache-Control', 'no-store, private');
})->with([
    ['json', 'json'],
    ['csv', 'zip'],
    ['xlsx', 'xlsx'],
]);

test('latest export links ignore files that are still being written', function () {
    Storage::disk('public')->put('exports/rodnik-from-2026-09-29_22-30-00.xlsx', 'complete');
    Storage::disk('public')->put('exports/.partial-rodnik-from-2026-09-30_22-30-00.xlsx', 'partial');

    $this->get('/docs/exports/xlsx/latest')
        ->assertRedirect('/exports/rodnik-from-2026-09-29_22-30-00.xlsx');
});

test('latest export link returns 404 when there is no export of that format', function () {
    Storage::disk('public')->put('exports/rodnik-from-2026-09-29_22-30-00.json', 'json');

    $this->get('/docs/exports/xlsx/latest')->assertNotFound();
});

test('unknown export formats are not handled by the latest route', function () {
    $this->get('/docs/exports/pdf/latest')->assertNotFound();
});

test('exports page lists the permanent links', function () {
    $this->get('/docs/exports')
        ->assertOk()
        ->assertSee(url('/docs/exports/json/latest'))
        ->assertSee(url('/docs/exports/csv/latest'))
        ->assertSee(url('/docs/exports/xlsx/latest'));
});
