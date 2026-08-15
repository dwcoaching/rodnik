<?php

declare(strict_types=1);

use App\Jobs\SendReportNotification;
use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake([SendReportNotification::class]);
    Storage::fake('tiles');
    Storage::fake('watered-tiles');
});

test('all report mutations require authentication', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'create' => ['POST', '/api/v1/reports'],
    'update' => ['PATCH', '/api/v1/reports/1'],
    'delete' => ['DELETE', '/api/v1/reports/1'],
]);

test('an authenticated user can create a normalized report', function () {
    $user = User::factory()->create();
    $spring = Spring::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/v1/reports', [
        'spring_id' => $spring->id,
        'visited_at' => '2026-08-15',
        'timezone' => 'Europe/Moscow',
        'state' => 'notfound',
        'quality' => 'good',
        'access_limited' => true,
        'littered' => true,
        'broken' => true,
        'comment' => 'The source could not be found.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.spring_id', $spring->id)
        ->assertJsonPath('data.state', 'notfound')
        ->assertJsonPath('data.quality', null)
        ->assertJsonPath('data.access_limited', false)
        ->assertJsonPath('data.photos', []);

    $report = Report::query()->sole();

    expect($report->user_id)->toBe($user->id)
        ->and($report->quality)->toBeNull()
        ->and($report->getRawOriginal('access_limited'))->toBeNull()
        ->and($report->getRawOriginal('littered'))->toBeNull()
        ->and($report->getRawOriginal('broken'))->toBeNull();

    Queue::assertPushed(SendReportNotification::class);
});

test('reports cannot be created for hidden or redirected springs', function (array $springAttributes) {
    Sanctum::actingAs(User::factory()->create(), ['*']);
    $spring = Spring::factory()->create($springAttributes);

    $this->postJson('/api/v1/reports', ['spring_id' => $spring->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('spring_id');
})->with([
    'hidden' => [['hidden_at' => now()]],
    'redirected' => [['redirect_to_spring_id' => fn () => Spring::factory()->create()->id]],
]);

test('an owner can patch report fields but cannot move or reassign the report', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $originalSpring = Spring::factory()->create();
    $otherSpring = Spring::factory()->create();
    $report = Report::factory()->create([
        'user_id' => $owner->id,
        'spring_id' => $originalSpring->id,
        'state' => 'running',
        'quality' => 'good',
    ]);
    Sanctum::actingAs($owner, ['*']);

    $this->patchJson("/api/v1/reports/{$report->id}", [
        'state' => 'dry',
        'quality' => 'good',
        'comment' => 'No flow today.',
        'spring_id' => $otherSpring->id,
        'user_id' => $otherUser->id,
        'hidden_at' => now()->toISOString(),
    ])
        ->assertOk()
        ->assertJsonPath('data.state', 'dry')
        ->assertJsonPath('data.quality', null)
        ->assertJsonPath('data.comment', 'No flow today.');

    $report->refresh();

    expect($report->spring_id)->toBe($originalSpring->id)
        ->and($report->user_id)->toBe($owner->id)
        ->and($report->hidden_at)->toBeNull();
});

test('a user cannot update another users report', function () {
    $report = Report::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->patchJson("/api/v1/reports/{$report->id}", ['comment' => 'Unauthorized'])
        ->assertForbidden();
});

test('an owner can soft hide a report and repeat the deletion safely', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $owner->id]);
    Sanctum::actingAs($owner, ['*']);

    $this->deleteJson("/api/v1/reports/{$report->id}")->assertNoContent();

    $report->refresh();
    expect($report->hidden_at)->not->toBeNull()
        ->and($report->hidden_by_author_id)->toBe($owner->id);

    $this->deleteJson("/api/v1/reports/{$report->id}")->assertNoContent();
    expect(Report::find($report->id))->not->toBeNull();
});

test('a user cannot hide another users report', function () {
    $report = Report::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->deleteJson("/api/v1/reports/{$report->id}")->assertForbidden();
});

test('report input validates enums and future dates', function (array $payload, string $field) {
    Sanctum::actingAs(User::factory()->create(), ['*']);
    $spring = Spring::factory()->create();

    $this->postJson('/api/v1/reports', ['spring_id' => $spring->id] + $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'state enum' => [['state' => 'flowing'], 'state'],
    'quality enum' => [['quality' => 'excellent'], 'quality'],
    'future date' => [['visited_at' => '2999-01-01', 'timezone' => 'Europe/Moscow'], 'visited_at'],
]);
