<?php

declare(strict_types=1);

use App\Library\Export\CsvTransformer;
use App\Library\Export\JsonTransformer;
use App\Livewire\Reports\Show;
use App\Models\Photo;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Http\Livewire\DeleteUserForm;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('photos');
    config(['cache.default' => 'array']);
});

test('deleted contributors remain anonymous in reports history the API and notifications', function (string $locale, string $prefix) {
    $author = User::factory()->create(['name' => 'Former Contributor', 'email' => 'former@example.test']);
    $spring = Spring::factory()->create();
    $report = Report::factory()->for($spring)->for($author)->create(['comment' => 'Clear water near the trail.']);
    $photo = Photo::factory()->for($report)->create();
    $revision = SpringRevision::forceCreate([
        'spring_id' => $spring->id,
        'user_id' => $author->id,
        'revision_type' => 'user',
        'new_name' => 'Trail spring',
    ]);

    $this->actingAs($author);
    Livewire::test(DeleteUserForm::class)
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasNoErrors();
    $this->assertGuest();

    $this->getJson("/api/v1/springs/{$spring->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.reports.0.id', $report->id)
        ->assertJsonPath('data.reports.0.author', null)
        ->assertJsonPath('data.reports.0.comment', $report->comment)
        ->assertJsonPath('data.reports.0.photos.0.id', $photo->id)
        ->assertDontSee($author->name)
        ->assertDontSee($author->email);

    app()->setLocale($locale);
    $anonymous = __('ui.common.anonymous');
    $report = $report->fresh(['spring', 'user', 'photos']);

    Livewire::test(Show::class, ['report' => $report])
        ->assertSee($anonymous)
        ->assertSee($report->comment)
        ->assertDontSee($author->name)
        ->assertDontSeeHtml('href="'.duo_route(['user' => $author->id]).'"');

    $teaser = Blade::render('<x-last-reports.teaser :report="$report" />', ['report' => $report]);
    expect($teaser)->toContain($anonymous)->not->toContain($author->name);

    $this->actingAs(User::factory()->create())
        ->get("{$prefix}/{$spring->id}/history")
        ->assertSuccessful()
        ->assertSee($anonymous)
        ->assertSee($report->comment)
        ->assertSee($revision->new_name)
        ->assertDontSee($author->name);

    $notification = view('telegram.report', ['report' => $report, 'photoCount' => 1, 'tags' => []])->render();
    expect($notification)->toContain('Anonymous')->toContain($report->comment)->not->toContain($author->name);
    expect(view('telegram.spring-revision', ['revision' => $revision->fresh()])->render())
        ->not->toContain($author->name);
})->with([
    'English' => ['en', ''],
    'Russian' => ['ru', '/ru'],
]);

test('regenerated export data retains reports photos and edits without account attribution', function () {
    $author = User::factory()->create(['name' => 'Export Contributor']);
    $spring = Spring::factory()->create();
    $report = Report::factory()->for($spring)->for($author)->create(['comment' => 'Public spring report.']);
    $photo = Photo::factory()->for($report)->create();
    $revision = SpringRevision::forceCreate([
        'spring_id' => $spring->id,
        'user_id' => $author->id,
        'revision_type' => 'user',
        'new_name' => 'Public spring edit',
    ]);

    $this->actingAs($author);
    Livewire::test(DeleteUserForm::class)->set('password', 'password')->call('deleteUser')->assertHasNoErrors();

    $springs = Spring::query()->whereKey($spring->id)->get();
    $json = (new JsonTransformer($springs))->transform();
    $csv = new CsvTransformer($springs);

    expect($json[0]['reports'][0])
        ->toMatchArray(['id' => $report->id, 'user' => 'Anonymous', 'user_id' => null, 'comment' => $report->comment])
        ->and($json[0]['reports'][0]['photos'])->toContain($photo->url)
        ->and($json[0]['edits'][0])->toMatchArray(['id' => $revision->id, 'user' => 'Anonymous', 'user_id' => null])
        ->and($csv->transformReports()[0])->toMatchArray(['id' => $report->id, 'user' => 'Anonymous', 'user_id' => null])
        ->and($csv->transformEdits()[0])->toMatchArray(['id' => $revision->id, 'user' => 'Anonymous', 'user_id' => null])
        ->and($csv->transformPhotos()[0]['url'])->toBe($photo->url);
});
