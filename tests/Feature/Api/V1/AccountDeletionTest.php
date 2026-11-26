<?php

declare(strict_types=1);

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('account deletion requires authentication', function () {
    $this->deleteJson('/api/v1/me', ['password' => 'anything'])->assertUnauthorized();
});

test('account deletion requires the current password', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-password')]);
    $token = $user->createToken('Test phone')->plainTextToken;

    $this->withToken($token)->deleteJson('/api/v1/me', ['password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect($user->fresh())->not->toBeNull();
});

test('a user can delete their account and keeps their reports anonymous', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-password')]);
    $report = Report::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('Test phone')->plainTextToken;

    $this->withToken($token)->deleteJson('/api/v1/me', ['password' => 'correct-password'])
        ->assertNoContent();

    expect($user->fresh())->toBeNull()
        ->and($report->fresh()->user_id)->toBeNull();
    $this->assertDatabaseCount('personal_access_tokens', 0);
});
