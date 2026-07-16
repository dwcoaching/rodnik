<?php

declare(strict_types=1);

use App\Enums\ReportAccess;
use App\Enums\ReportQuality;
use App\Enums\ReportState;
use App\Jobs\SendReportNotification;
use App\Livewire\Reports\Create as CreateReport;
use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function fakeReportStoreSideEffects(): void
{
    Queue::fake([SendReportNotification::class]);
    Storage::fake('tiles');
    Storage::fake('watered-tiles');
}

test('report form shows the details group with all problem chips', function () {
    $spring = Spring::factory()->create();

    Livewire::test(CreateReport::class, ['springId' => $spring->id, 'reportId' => null])
        ->assertSee('Details')
        ->assertDontSee('Problems')
        ->assertSee('New report')
        ->assertSee('Visit date')
        ->assertSee('Do not specify')
        ->assertSee('Good water')
        ->assertSee('Poor water')
        ->assertSee('Add report')
        ->assertDontSee('New Report')
        ->assertDontSee('Good Water')
        ->assertDontSee('Add Report')
        ->assertDontSee('please describe in the comment')
        ->assertSee('Additional details')
        ->assertSee('Describe what you observed…')
        ->assertSee('No access')
        ->assertSee('Limited access')
        ->assertSee('Littered')
        ->assertSee('Ruined');
});

test('report form increases all non-help text by two pixels', function () {
    $spring = Spring::factory()->create();

    Livewire::test(CreateReport::class, ['springId' => $spring->id, 'reportId' => null])
        ->assertSeeHtml('data-test="report-create-form"')
        ->assertSeeHtml('text-[18px]/[28px]')
        ->assertSeeHtml('[&_.text-xs]:text-[14px]/[20px]')
        ->assertSeeHtml('[&_.text-sm]:text-[16px]/[24px]')
        ->assertSeeHtml('[&_.text-lg]:text-[20px]/[30px]')
        ->assertSeeHtml('[&_.text-2xl]:text-[26px]/[34px]')
        ->assertSeeHtml('[&_.btn]:text-[16px]/[24px]')
        ->assertSeeHtml('btn h-11 font-bold btn-primary');
});

test('report form group titles toggle explanations for every value', function () {
    $spring = Spring::factory()->create();

    $component = Livewire::test(CreateReport::class, ['springId' => $spring->id, 'reportId' => null])
        ->assertSeeHtml('data-test="condition-info-toggle"')
        ->assertSeeHtml('data-test="water-quality-info-toggle"')
        ->assertSeeHtml('data-test="problems-info-toggle"')
        ->assertSeeHtml('@click="infoOpen = ! infoOpen"')
        ->assertSeeHtml('x-show="infoOpen"')
        ->assertSeeHtml('rounded-lg bg-gray-200')
        ->assertSeeHtml('text-base leading-relaxed')
        ->assertSeeHtml('role="region"')
        ->assertSeeHtml('aria-labelledby="condition-info-toggle"')
        ->assertSee('Water is present, even if the flow is very weak.')
        ->assertSee('The source is dry; there is not enough water to fill a cup.')
        ->assertSee('Neither the source nor any trace of it can be found. This may indicate a mapping error.')
        ->assertSee('Use your own judgment.')
        ->assertSee('A “good” rating does not guarantee that the water')
        ->assertSee('is safe to drink.')
        ->assertSee('The source is locked, located behind a wall, or otherwise inaccessible.')
        ->assertSee('Access is limited by seasonal or time restrictions, hazards (such as a steep slope or dense vegetation), or the need for special equipment (such as a bucket for a well).')
        ->assertSee('There is rubbish around or inside the source.')
        ->assertSee('The source is severely damaged or no longer functional.');

    expect(mb_substr_count($component->html(), 'x-data="{ infoOpen: false }"'))->toBe(3);
});

test('condition and problem chips are wired to their states', function () {
    $spring = Spring::factory()->create();

    Livewire::test(CreateReport::class, ['springId' => $spring->id, 'reportId' => null])
        ->assertSee('Has water')
        ->assertSee('No water')
        ->assertSee('Water source not found')
        ->assertSeeHtml("state = state == 'running' ? null : 'running'")
        ->assertSeeHtml("state = state == 'dry' ? null : 'dry'")
        ->assertSeeHtml("state = state == 'notfound' ? null : 'notfound'")
        ->assertSeeHtml("access = access == 'no' ? null : 'no'")
        ->assertSeeHtml("access = access == 'limited' ? null : 'limited'");
});

test('report is stored with problem flags', function () {
    fakeReportStoreSideEffects();

    $spring = Spring::factory()->create();

    Livewire::test(CreateReport::class, ['springId' => $spring->id, 'reportId' => null])
        ->set('state', 'running')
        ->set('quality', 'good')
        ->set('access', 'limited')
        ->set('littered', true)
        ->set('ruined', true)
        ->call('store')
        ->assertHasNoErrors();

    $report = $spring->reports()->firstOrFail();

    expect($report->state)->toBe(ReportState::Running);
    expect($report->quality)->toBe(ReportQuality::Good);
    expect($report->access)->toBe(ReportAccess::Limited);
    expect($report->littered)->toBeTrue();
    expect($report->ruined)->toBeTrue();
});

test('unchecked problem flags are stored as null, never as zero', function () {
    fakeReportStoreSideEffects();

    $spring = Spring::factory()->create();

    Livewire::test(CreateReport::class, ['springId' => $spring->id, 'reportId' => null])
        ->set('state', 'running')
        ->call('store')
        ->assertHasNoErrors();

    $report = $spring->reports()->firstOrFail();

    expect($report->getRawOriginal('access'))->toBeNull();
    expect($report->getRawOriginal('littered'))->toBeNull();
    expect($report->getRawOriginal('ruined'))->toBeNull();
});

test('not found clears quality and other problem flags on store', function () {
    fakeReportStoreSideEffects();

    $spring = Spring::factory()->create();

    Livewire::test(CreateReport::class, ['springId' => $spring->id, 'reportId' => null])
        ->set('state', 'notfound')
        ->set('quality', 'good')
        ->set('access', 'limited')
        ->set('littered', true)
        ->set('ruined', true)
        ->call('store')
        ->assertHasNoErrors();

    $report = $spring->reports()->firstOrFail();

    expect($report->state)->toBe(ReportState::NotFound);
    expect($report->quality)->toBeNull();
    expect($report->access)->toBeNull();
    expect($report->getRawOriginal('littered'))->toBeNull();
    expect($report->getRawOriginal('ruined'))->toBeNull();
});

test('short comment handles null, short and long comments', function () {
    $report = new Report();

    $report->comment = null;
    expect($report->short_comment)->toBeNull();

    $report->comment = 'Clean and cold.';
    expect($report->short_comment)->toBe('Clean and cold.');

    $report->comment = str_repeat('a', 200);
    expect($report->short_comment)->toBe(str_repeat('a', 150).'...');
});

test('report condition fields reject values outside their enums', function (string $field, string $value) {
    fakeReportStoreSideEffects();

    $spring = Spring::factory()->create();

    Livewire::test(CreateReport::class, ['springId' => $spring->id, 'reportId' => null])
        ->set('state', 'running')
        ->set($field, $value)
        ->call('store')
        ->assertHasErrors([$field]);

    expect($spring->reports()->count())->toBe(0);
})->with([
    'state' => ['state', 'flowing'],
    'quality' => ['quality', 'excellent'],
    'access' => ['access', 'sometimes'],
]);

test('problem flags round-trip when editing a report', function () {
    fakeReportStoreSideEffects();

    $user = User::factory()->create();
    $report = Report::factory()->create([
        'user_id' => $user->id,
        'state' => 'running',
        'quality' => 'bad',
        'access' => 'no',
        'littered' => true,
        'ruined' => null,
    ]);

    Livewire::actingAs($user)
        ->test(CreateReport::class, ['springId' => $report->spring_id, 'reportId' => $report->id])
        ->assertSee('Save changes')
        ->assertDontSee('Save Changes')
        ->assertSet('access', 'no')
        ->assertSet('littered', true)
        ->assertSet('ruined', false)
        ->set('access', 'limited')
        ->set('littered', false)
        ->call('store')
        ->assertHasNoErrors();

    $report->refresh();

    expect($report->access)->toBe(ReportAccess::Limited);
    expect($report->getRawOriginal('littered'))->toBeNull();
    expect($report->getRawOriginal('ruined'))->toBeNull();
});

test('validation failure does not wipe condition selections', function () {
    fakeReportStoreSideEffects();

    $spring = Spring::factory()->create();

    Livewire::test(CreateReport::class, ['springId' => $spring->id, 'reportId' => null])
        ->set('state', 'running')
        ->set('quality', 'good')
        ->set('access', 'limited')
        ->set('littered', true)
        ->set('ruined', true)
        ->set('visited_at', 'not-a-date')
        ->call('store')
        ->assertHasErrors(['visited_at'])
        ->assertSet('quality', 'good')
        ->assertSet('access', 'limited')
        ->assertSet('littered', true)
        ->assertSet('ruined', true);

    expect($spring->reports()->count())->toBe(0);
});
