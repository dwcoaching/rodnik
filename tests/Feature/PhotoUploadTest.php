<?php

declare(strict_types=1);

use App\Jobs\SendReportNotification;
use App\Livewire\Reports\Create as CreateReport;
use App\Models\Photo;
use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function fakePhotoUpload(string $name = 'spring.jpg', int $width = 640, int $height = 480, int $size = 500): UploadedFile
{
    return UploadedFile::fake()->image($name, $width, $height)->size($size);
}

function fakePhotoFile(Photo $photo): void
{
    Storage::disk('photos')->put($photo->filename, 'photo');
}

function fakeReportSideEffects(): void
{
    Queue::fake([SendReportNotification::class]);
    Storage::fake('tiles');
    Storage::fake('watered-tiles');
}

test('photos upload outside livewire and return photo metadata', function () {
    Storage::fake('photos');

    $response = $this->postJson(route('photos.uploads.store'), [
        'photo' => fakePhotoUpload(),
    ]);

    $response->assertOk()
        ->assertJsonStructure(['id', 'url', 'width', 'height']);

    $photo = Photo::findOrFail($response->json('id'));

    expect($photo->report_id)->toBeNull();
    expect($photo->width)->toBe(640);
    expect($photo->height)->toBe(480);
    Storage::disk('photos')->assertExists($photo->filename);
});

test('authenticated user can upload a photo while editing their own report', function () {
    Storage::fake('photos');
    $user = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson(route('photos.uploads.store'), [
        'report_id' => $report->id,
        'photo' => fakePhotoUpload(),
    ]);

    $response->assertOk();

    $photo = Photo::findOrFail($response->json('id'));

    expect($photo->report_id)->toBeNull();
    Storage::disk('photos')->assertExists($photo->filename);
});

test('guest cannot upload a photo while editing an existing report', function () {
    Storage::fake('photos');
    $report = Report::factory()->create();

    $response = $this->postJson(route('photos.uploads.store'), [
        'report_id' => $report->id,
        'photo' => fakePhotoUpload(),
    ]);

    $response->assertForbidden();
    expect(Photo::count())->toBe(0);
});

test('user cannot upload a photo while editing another users report', function () {
    Storage::fake('photos');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($otherUser)->postJson(route('photos.uploads.store'), [
        'report_id' => $report->id,
        'photo' => fakePhotoUpload(),
    ]);

    $response->assertForbidden();
    expect(Photo::count())->toBe(0);
});

test('photo upload requires a photo', function () {
    $this->postJson(route('photos.uploads.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photo');
});

test('photo upload rejects non image files', function () {
    $this->postJson(route('photos.uploads.store'), [
        'photo' => UploadedFile::fake()->create('note.txt', 1, 'text/plain'),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photo');
});

test('photo upload rejects oversized images', function () {
    $this->postJson(route('photos.uploads.store'), [
        'photo' => fakePhotoUpload(size: 10241),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photo');
});

test('photo upload with an unknown report id is rejected', function () {
    Storage::fake('photos');
    $this->actingAs(User::factory()->create())
        ->postJson(route('photos.uploads.store'), [
            'report_id' => 999999999,
            'photo' => fakePhotoUpload(),
        ])
        ->assertNotFound();
});

test('unattached photo cannot be deleted through the upload endpoint', function () {
    Storage::fake('photos');

    $upload = $this->postJson(route('photos.uploads.store'), [
        'photo' => fakePhotoUpload(),
    ]);

    $photo = Photo::findOrFail($upload->json('id'));

    // Orphan photos are left for a background prune job, never deleted by regular users.
    $this->actingAs(User::factory()->create())
        ->deleteJson(route('photos.uploads.destroy', $photo))
        ->assertForbidden();

    expect(Photo::find($photo->id))->not->toBeNull();
    Storage::disk('photos')->assertExists($photo->filename);
});

test('superadmin can delete an unattached photo', function () {
    Storage::fake('photos');

    $upload = $this->postJson(route('photos.uploads.store'), [
        'photo' => fakePhotoUpload(),
    ]);

    $photo = Photo::findOrFail($upload->json('id'));

    $this->actingAs(User::factory()->create(['is_superadmin' => true]))
        ->deleteJson(route('photos.uploads.destroy', $photo))
        ->assertNoContent();

    expect(Photo::find($photo->id))->toBeNull();
    Storage::disk('photos')->assertMissing($photo->filename);
});

test('guest cannot delete an attached photo', function () {
    Storage::fake('photos');

    $report = Report::factory()->create();
    $photo = Photo::factory()->create(['report_id' => $report->id]);
    fakePhotoFile($photo);

    $this->deleteJson(route('photos.uploads.destroy', $photo))->assertForbidden();

    expect(Photo::find($photo->id))->not->toBeNull();
    Storage::disk('photos')->assertExists($photo->filename);
});

test('owner can delete a photo attached to their report', function () {
    Storage::fake('photos');

    $user = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $user->id]);
    $photo = Photo::factory()->create(['report_id' => $report->id]);
    fakePhotoFile($photo);

    $this->actingAs($user)
        ->deleteJson(route('photos.uploads.destroy', $photo))
        ->assertNoContent();

    expect(Photo::find($photo->id))->toBeNull();
    Storage::disk('photos')->assertMissing($photo->filename);
});

test('user cannot delete a photo attached to another users report', function () {
    Storage::fake('photos');

    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $owner->id]);
    $photo = Photo::factory()->create(['report_id' => $report->id]);
    fakePhotoFile($photo);

    $this->actingAs($otherUser)
        ->deleteJson(route('photos.uploads.destroy', $photo))
        ->assertForbidden();

    expect(Photo::find($photo->id))->not->toBeNull();
    Storage::disk('photos')->assertExists($photo->filename);
});

test('superadmin can delete a photo attached to any report', function () {
    Storage::fake('photos');

    $superadmin = User::factory()->create(['is_superadmin' => true]);

    $report = Report::factory()->create();
    $photo = Photo::factory()->create(['report_id' => $report->id]);
    fakePhotoFile($photo);

    $this->actingAs($superadmin)
        ->deleteJson(route('photos.uploads.destroy', $photo))
        ->assertNoContent();

    expect(Photo::find($photo->id))->toBeNull();
    Storage::disk('photos')->assertMissing($photo->filename);
});

test('deleting a missing uploaded photo returns not found', function () {
    $this->deleteJson(route('photos.uploads.destroy', 999999999))->assertNotFound();
});

test('new report attaches unattached photos in submitted order', function () {
    Storage::fake('photos');
    fakeReportSideEffects();

    $spring = Spring::factory()->create();
    $first = Photo::factory()->create();
    $second = Photo::factory()->create();
    $third = Photo::factory()->create();

    $sortablePhotos = [
        ['value' => $second->id, 'order' => 1],
        ['value' => $third->id, 'order' => 2],
        ['value' => $first->id, 'order' => 3],
    ];

    Livewire::test(CreateReport::class, ['springId' => $spring->id, 'reportId' => null])
        ->set('sortablePhotos', $sortablePhotos)
        ->assertSet('sortablePhotos', $sortablePhotos)
        ->call('store')
        ->assertHasNoErrors();

    $report = Report::where('spring_id', $spring->id)->sole();

    expect($report->spring_id)->toBe($spring->id)
        ->and($second->fresh()->report_id)->toBe($report->id)
        ->and($second->fresh()->order)->toBe(1)
        ->and($third->fresh()->report_id)->toBe($report->id)
        ->and($third->fresh()->order)->toBe(2)
        ->and($first->fresh()->report_id)->toBe($report->id)
        ->and($first->fresh()->order)->toBe(3);
});

test('report save attaches any unattached orphan photo listed in sortable photos', function () {
    Storage::fake('photos');
    fakeReportSideEffects();

    $user = User::factory()->create();
    $spring = Spring::factory()->create();
    $photo = Photo::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateReport::class, ['springId' => $spring->id, 'reportId' => null])
        ->set('sortablePhotos', [
            ['value' => $photo->id, 'order' => 1],
        ])
        ->call('store')
        ->assertHasNoErrors();

    $report = Report::where('spring_id', $spring->id)->sole();

    expect($photo->fresh()->report_id)->toBe($report->id)
        ->and($report->user_id)->toBe($user->id);
});

test('editing report reorders existing photos', function () {
    Storage::fake('photos');
    fakeReportSideEffects();

    $user = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $user->id]);
    $first = Photo::factory()->create(['report_id' => $report->id, 'order' => 1]);
    $second = Photo::factory()->create(['report_id' => $report->id, 'order' => 2]);
    $third = Photo::factory()->create(['report_id' => $report->id, 'order' => 3]);

    Livewire::actingAs($user)
        ->test(CreateReport::class, ['springId' => $report->spring_id, 'reportId' => $report->id])
        ->set('sortablePhotos', [
            ['value' => $third->id, 'order' => 1],
            ['value' => $first->id, 'order' => 2],
            ['value' => $second->id, 'order' => 3],
        ])
        ->call('store')
        ->assertHasNoErrors();

    expect($third->fresh()->order)->toBe(1)
        ->and($first->fresh()->order)->toBe(2)
        ->and($second->fresh()->order)->toBe(3);
});

test('editing report attaches newly uploaded unattached photos', function () {
    Storage::fake('photos');
    fakeReportSideEffects();

    $user = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $user->id]);
    $existing = Photo::factory()->create(['report_id' => $report->id, 'order' => 1]);
    $uploaded = Photo::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateReport::class, ['springId' => $report->spring_id, 'reportId' => $report->id])
        ->set('sortablePhotos', [
            ['value' => $existing->id, 'order' => 1],
            ['value' => $uploaded->id, 'order' => 2],
        ])
        ->call('store')
        ->assertHasNoErrors();

    expect($existing->fresh()->report_id)->toBe($report->id)
        ->and($existing->fresh()->order)->toBe(1)
        ->and($uploaded->fresh()->report_id)->toBe($report->id)
        ->and($uploaded->fresh()->order)->toBe(2);
});

test('editing report detaches removed photos', function () {
    Storage::fake('photos');
    fakeReportSideEffects();

    $user = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $user->id]);
    $kept = Photo::factory()->create(['report_id' => $report->id, 'order' => 1]);
    $removed = Photo::factory()->create(['report_id' => $report->id, 'order' => 2]);

    Livewire::actingAs($user)
        ->test(CreateReport::class, ['springId' => $report->spring_id, 'reportId' => $report->id])
        ->set('sortablePhotos', [
            ['value' => $kept->id, 'order' => 1],
        ])
        ->call('store')
        ->assertHasNoErrors();

    expect($kept->fresh()->report_id)->toBe($report->id)
        ->and($removed->fresh()->report_id)->toBeNull();
});

test('editing report does not attach photos already attached to another report', function () {
    Storage::fake('photos');
    fakeReportSideEffects();

    $user = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $user->id]);
    $otherReport = Report::factory()->create();
    $ownPhoto = Photo::factory()->create(['report_id' => $report->id, 'order' => 1]);
    $otherPhoto = Photo::factory()->create(['report_id' => $otherReport->id, 'order' => 7]);

    Livewire::actingAs($user)
        ->test(CreateReport::class, ['springId' => $report->spring_id, 'reportId' => $report->id])
        ->set('sortablePhotos', [
            ['value' => $otherPhoto->id, 'order' => 1],
            ['value' => $ownPhoto->id, 'order' => 2],
        ])
        ->call('store')
        ->assertHasNoErrors();

    expect($otherPhoto->fresh()->report_id)->toBe($otherReport->id)
        ->and($otherPhoto->fresh()->order)->toBe(7)
        ->and($ownPhoto->fresh()->report_id)->toBe($report->id)
        ->and($ownPhoto->fresh()->order)->toBe(2);
});

test('user cannot save photo changes on another users report', function () {
    Storage::fake('photos');
    fakeReportSideEffects();

    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($otherUser)
        ->test(CreateReport::class, ['springId' => $report->spring_id, 'reportId' => $report->id])
        ->assertForbidden();
});

test('old standalone photo batch page is removed', function () {
    $this->get('/photos/create')->assertNotFound();
});
