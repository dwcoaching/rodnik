<?php

declare(strict_types=1);

use App\Actions\DeleteAccountAction;
use App\Library\Export\CsvWriter;
use App\Library\Export\ExportLock;
use App\Library\Export\JsonWriter;
use App\Library\Export\XlsxWriter;
use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    config(['cache.default' => 'array']);
});

test('a queued personal export cannot recreate account files after deletion', function (string $writerClass) {
    $author = User::factory()->create();
    $spring = Spring::factory()->create();
    Report::factory()->for($spring)->for($author)->create();
    $writer = (new $writerClass(Spring::query()->whereKey($spring->id)))->forUser($author);

    $this->actingAs($author);
    app(DeleteAccountAction::class)($author);

    try {
        $writer->save();
        $this->fail('An export for a deleted account must not be created.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }

    expect(Storage::disk('public')->allFiles('exports'))->toBe([]);
})->with([JsonWriter::class, CsvWriter::class, XlsxWriter::class]);

test('exports hold the deletion lock while reading and writing contribution data', function () {
    $spring = Spring::factory()->create();
    $report = Report::factory()->for($spring)->create(['user_id' => null]);
    $query = Spring::query()->whereKey($spring->id)->afterQuery(function ($springs) {
        expect(Cache::lock(ExportLock::NAME, ExportLock::SECONDS)->get())->toBeFalse();

        return $springs;
    });
    $writer = new JsonWriter($query);

    $filename = $writer->save();
    $json = json_decode(Storage::disk('public')->get('exports/'.$filename), true, flags: JSON_THROW_ON_ERROR);

    expect($json[0]['reports'][0])->toMatchArray(['id' => $report->id, 'user' => 'Anonymous', 'user_id' => null]);

    $lock = Cache::lock(ExportLock::NAME, ExportLock::SECONDS);
    expect($lock->get())->toBeTrue();
    $lock->release();
});

test('all contribution export formats still write retained anonymous reports', function (string $writerClass, string $extension) {
    $spring = Spring::factory()->create();
    Report::factory()->for($spring)->create(['user_id' => null]);
    Storage::disk('public')->makeDirectory('exports');

    $filename = (new $writerClass(Spring::query()->whereKey($spring->id)))->save();

    expect($filename)->toEndWith('.'.$extension)
        ->and(Storage::disk('public')->size('exports/'.$filename))->toBeGreaterThan(0);
})->with([
    'JSON' => [JsonWriter::class, 'json'],
    'CSV archive' => [CsvWriter::class, 'zip'],
    'Excel' => [XlsxWriter::class, 'xlsx'],
]);
