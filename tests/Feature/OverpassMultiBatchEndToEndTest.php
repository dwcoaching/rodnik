<?php

use App\Jobs\PruneMissingOSMSprings;
use App\Library\Overpass;
use App\Models\OverpassBatch;
use App\Models\Spring;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('two-batch end-to-end: B disappears and is pruned, A remains via fast path', function () {
    // Batch 1: both springs appear
    $batch1 = OverpassBatch::create([]);
    $batch1->coverage = 100;
    $batch1->parse_status = 'parsed';
    $batch1->save();

    $json1 = json_decode(file_get_contents(base_path('tests/stubs/overpass-batch-1.json')));
    Overpass::parse($json1, $batch1->id);

    $springA = Spring::where('osm_node_id', 1001)->first();
    $springB = Spring::where('osm_node_id', 1002)->first();

    expect($springA)->not->toBeNull();
    expect($springB)->not->toBeNull();
    expect($springA->last_seen_overpass_batch_id)->toBe($batch1->id);
    expect($springB->last_seen_overpass_batch_id)->toBe($batch1->id);

    // Prune against batch 1 - nothing missing, both stay
    (new PruneMissingOSMSprings($batch1))->handle();
    expect(Spring::find($springA->id))->not->toBeNull();
    expect(Spring::find($springB->id))->not->toBeNull();

    // Batch 2: only A present (B has disappeared from OSM)
    $batch2 = OverpassBatch::create([]);
    $batch2->coverage = 100;
    $batch2->parse_status = 'parsed';
    $batch2->save();

    $json2 = json_decode(file_get_contents(base_path('tests/stubs/overpass-batch-2-a-only.json')));
    $stats = Overpass::parse($json2, $batch2->id);

    // A hit the version fast path
    expect($stats->unchanged)->toBe(1);

    $springA->refresh();
    expect($springA->last_seen_overpass_batch_id)->toBe($batch2->id);

    // B is still stamped with batch1.id (untouched by batch 2 parse)
    $springB->refresh();
    expect($springB->last_seen_overpass_batch_id)->toBe($batch1->id);

    // Prune against batch 2: B gets deleted, A stays
    (new PruneMissingOSMSprings($batch2))->handle();

    expect(Spring::find($springA->id))->not->toBeNull();
    expect(Spring::find($springB->id))->toBeNull();
});

test('two-batch: disappearing spring with user report is hidden, not pruned', function () {
    $batch1 = OverpassBatch::create([]);
    $batch1->coverage = 100;
    $batch1->parse_status = 'parsed';
    $batch1->save();

    $json1 = json_decode(file_get_contents(base_path('tests/stubs/overpass-batch-1.json')));
    Overpass::parse($json1, $batch1->id);

    $springB = Spring::where('osm_node_id', 1002)->first();

    \DB::table('reports')->insert([
        'spring_id' => $springB->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $batch2 = OverpassBatch::create([]);
    $batch2->coverage = 100;
    $batch2->parse_status = 'parsed';
    $batch2->save();

    $json2 = json_decode(file_get_contents(base_path('tests/stubs/overpass-batch-2-a-only.json')));
    Overpass::parse($json2, $batch2->id);

    (new PruneMissingOSMSprings($batch2))->handle();

    $springB->refresh();
    expect(Spring::find($springB->id))->not->toBeNull();
    expect($springB->hidden_at)->not->toBeNull();
});
