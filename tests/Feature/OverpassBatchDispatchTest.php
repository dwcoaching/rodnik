<?php

use App\Jobs\CleanupOSMSprings;
use App\Jobs\PruneMissingOSMSprings;
use App\Jobs\RemoveOlderOverpassArtifacts;
use App\Models\OverpassBatch;
use App\Models\OverpassImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('updateParsedPercentage at 100% dispatches PruneMissingOSMSprings alongside other terminal jobs', function () {
    Queue::fake();

    $batch = OverpassBatch::create([]);
    $batch->coverage = 100;
    $batch->save();

    $import = new OverpassImport();
    $import->overpass_batch_id = $batch->id;
    $import->latitude_from = -90;
    $import->latitude_to = 90;
    $import->longitude_from = -180;
    $import->longitude_to = 180;
    $import->started_at = now();
    $import->fetched_at = now();
    $import->parsed_at = now();
    $import->response_code = 200;
    $import->has_remarks = 0;
    $import->ground_up = false;
    $import->save();

    $batch->updateParsedPercentage();

    Queue::assertPushed(CleanupOSMSprings::class);
    Queue::assertPushed(PruneMissingOSMSprings::class);
    Queue::assertPushed(RemoveOlderOverpassArtifacts::class);
});
