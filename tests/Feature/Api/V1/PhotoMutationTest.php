<?php

declare(strict_types=1);

use App\Models\Photo;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('photos');
});

test('photo mutations require authentication', function () {
    $this->postJson('/api/v1/reports/1/photos')->assertUnauthorized();
    $this->deleteJson('/api/v1/photos/1')->assertUnauthorized();
});

test('an owner can upload photos directly to a report in deterministic order', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $owner->id]);
    Photo::factory()->create(['report_id' => $report->id, 'order' => 1]);
    Sanctum::actingAs($owner, ['*']);

    $response = $this->postJson("/api/v1/reports/{$report->id}/photos", [
        'photo' => UploadedFile::fake()->image('spring.webp', 640, 480)->size(500),
        'latitude' => 55.7558,
        'longitude' => 37.6173,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.width', 640)
        ->assertJsonPath('data.height', 480)
        ->assertJsonStructure(['data' => ['id', 'url', 'width', 'height']]);

    $photo = Photo::findOrFail($response->json('data.id'));
    expect($photo->report_id)->toBe($report->id)
        ->and($photo->order)->toBe(2)
        ->and($photo->latitude)->not->toBeNull();
    Storage::disk('photos')->assertExists($photo->filename);
});

test('a user cannot upload to another users report or a hidden report', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $owner->id]);
    Sanctum::actingAs($otherUser, ['*']);

    $this->postJson("/api/v1/reports/{$report->id}/photos", [
        'photo' => UploadedFile::fake()->image('spring.jpg'),
    ])->assertForbidden();

    $report->hidden_at = now();
    $report->save();
    Sanctum::actingAs($owner, ['*']);

    $this->postJson("/api/v1/reports/{$report->id}/photos", [
        'photo' => UploadedFile::fake()->image('spring.jpg'),
    ])->assertForbidden();
});

test('photo uploads validate image type and size', function (UploadedFile $file) {
    $owner = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $owner->id]);
    Sanctum::actingAs($owner, ['*']);

    $this->postJson("/api/v1/reports/{$report->id}/photos", ['photo' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photo');
})->with([
    'not an image' => fn () => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
    'over 10 MB' => fn () => UploadedFile::fake()->image('large.jpg')->size(10241),
]);

test('an owner can physically delete an attached photo', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $owner->id]);
    $photo = Photo::factory()->create(['report_id' => $report->id]);
    Storage::disk('photos')->put($photo->filename, 'image');
    Sanctum::actingAs($owner, ['*']);

    $this->deleteJson("/api/v1/photos/{$photo->id}")->assertNoContent();

    $this->assertModelMissing($photo);
    Storage::disk('photos')->assertMissing($photo->filename);
});

test('a user cannot delete another users photo', function () {
    $photo = Photo::factory()->create(['report_id' => Report::factory()]);
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->deleteJson("/api/v1/photos/{$photo->id}")->assertForbidden();
    $this->assertModelExists($photo);
});
