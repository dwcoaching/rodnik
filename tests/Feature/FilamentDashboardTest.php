<?php

use App\Models\OverpassBatch;
use App\Models\User;

test('admin can render the filament dashboard', function () {
    $batch = new OverpassBatch();
    $batch->parse_status = 'parsed';
    $batch->coverage = 100;
    $batch->save();

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get('/filament')
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Water Sources');
});
