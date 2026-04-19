<?php

use App\Models\Spring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function attachReport(User $user, Spring $spring): void
{
    \DB::table('reports')->insert([
        'spring_id' => $spring->id,
        'user_id' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('user sees hidden spring they reported on (personal page remains populated)', function () {
    $user = User::factory()->create();
    $spring = Spring::factory()->create(['hidden_at' => now()]);

    attachReport($user, $spring);

    $user->refresh();
    $springIds = $user->springs->pluck('id')->all();
    expect($springIds)->toContain($spring->id);
});

test('user still sees their non-hidden springs (regression guard)', function () {
    $user = User::factory()->create();
    $spring = Spring::factory()->create(['hidden_at' => null]);

    attachReport($user, $spring);

    $user->refresh();
    $springIds = $user->springs->pluck('id')->all();
    expect($springIds)->toContain($spring->id);
});
