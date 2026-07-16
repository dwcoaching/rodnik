<?php

declare(strict_types=1);

use App\Enums\ReportAccess;
use App\Enums\ReportQuality;
use App\Enums\ReportState;
use App\Filament\Resources\ReportResource\Pages\ListReports;
use App\Http\Resources\ExportedReportResource;
use App\Library\EnrichGPX;
use App\Library\Export\CsvTransformer;
use App\Library\Export\JsonTransformer;
use App\Livewire\Reports\Show as ShowReport;
use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use App\Notifications\ReportNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('report condition values cast to enums while retaining string storage', function () {
    $report = Report::factory()->create([
        'state' => ReportState::Running,
        'quality' => ReportQuality::Good,
        'access' => ReportAccess::Limited,
    ]);

    expect($report->state)->toBe(ReportState::Running)
        ->and($report->quality)->toBe(ReportQuality::Good)
        ->and($report->access)->toBe(ReportAccess::Limited)
        ->and($report->getRawOriginal('state'))->toBe(ReportState::Running->value)
        ->and($report->getRawOriginal('quality'))->toBe(ReportQuality::Good->value)
        ->and($report->getRawOriginal('access'))->toBe(ReportAccess::Limited->value);
});

test('report history renders enum titles', function () {
    $user = User::factory()->create();
    $report = Report::factory()->create([
        'state' => ReportState::Running,
        'quality' => ReportQuality::Uncertain,
        'access' => ReportAccess::Limited,
    ]);

    $this->actingAs($user)
        ->get(route('springs.history', $report->spring))
        ->assertSuccessful()
        ->assertSee('State: Has water')
        ->assertSee('Quality: Questionable water')
        ->assertSee('Access: Limited access');
});

test('problem badges render on report details and report teasers', function () {
    $report = Report::factory()->create([
        'state' => 'running',
        'quality' => 'uncertain',
        'access' => 'limited',
        'littered' => true,
        'ruined' => true,
    ]);

    Livewire::test(ShowReport::class, ['report' => $report])
        ->assertSee('Questionable water')
        ->assertSee('Limited access')
        ->assertSee('Littered')
        ->assertSee('Ruined');

    $teaser = Blade::render('<x-last-reports.teaser :report="$report" />', [
        'report' => $report->load(['spring', 'user', 'photos']),
    ]);

    expect($teaser)
        ->toContain('Has water')
        ->toContain('Questionable water')
        ->toContain('Limited access')
        ->toContain('border border-amber-200 bg-amber-50 text-amber-900')
        ->toContain('Littered')
        ->toContain('border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-900">Littered</span>')
        ->toContain('Ruined')
        ->toContain('border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-900">Ruined</span>');
});

test('problem badges distinguish no access and omit unreported problems', function () {
    $successReport = Report::factory()->make([
        'state' => 'running',
        'quality' => 'good',
        'access' => null,
        'littered' => null,
        'ruined' => null,
    ]);

    $successBadges = Blade::render('<x-report-condition-badges :report="$report" />', [
        'report' => $successReport,
    ]);

    expect($successBadges)
        ->toContain('class="inline-flex items-center rounded-sm px-2.5 py-0.5 text-xs font-medium border border-green-200 bg-green-50 text-green-900">Has water</span>')
        ->toContain('class="inline-flex items-center rounded-sm px-2.5 py-0.5 text-xs font-medium border border-green-200 bg-green-50 text-green-900">Good water</span>')
        ->not->toContain('bg-green-600 text-white');

    $noAccessReport = Report::factory()->make([
        'state' => null,
        'quality' => null,
        'access' => 'no',
        'littered' => null,
        'ruined' => null,
    ]);

    $noAccessBadges = Blade::render('<x-report-condition-badges :report="$report" />', [
        'report' => $noAccessReport,
    ]);

    expect($noAccessBadges)
        ->toContain('No access')
        ->toContain('border border-red-200 bg-red-50 text-red-900')
        ->not->toContain('Limited access')
        ->not->toContain('Littered')
        ->not->toContain('Ruined');

    $emptyReport = Report::factory()->make([
        'state' => null,
        'quality' => null,
        'access' => null,
        'littered' => null,
        'ruined' => null,
    ]);

    $emptyBadges = Blade::render('<x-report-condition-badges :report="$report" />', [
        'report' => $emptyReport,
    ]);

    expect(mb_trim(strip_tags($emptyBadges)))->toBe('');
});

test('danger condition badges use subtle red styling', function () {
    $dryAndPoorReport = Report::factory()->make([
        'state' => 'dry',
        'quality' => 'bad',
        'access' => null,
        'littered' => null,
        'ruined' => null,
    ]);

    $dryAndPoorBadges = Blade::render('<x-report-condition-badges :report="$report" />', [
        'report' => $dryAndPoorReport,
    ]);

    expect($dryAndPoorBadges)
        ->toContain('class="inline-flex items-center rounded-sm px-2.5 py-0.5 text-xs font-medium border border-red-200 bg-red-50 text-red-900">Dry</span>')
        ->toContain('class="inline-flex items-center rounded-sm px-2.5 py-0.5 text-xs font-medium border border-red-200 bg-red-50 text-red-900">Poor water</span>')
        ->not->toContain('bg-red-600 text-white');

    $notFoundReport = Report::factory()->make([
        'state' => 'notfound',
        'quality' => null,
        'access' => null,
        'littered' => null,
        'ruined' => null,
    ]);

    $notFoundBadges = Blade::render('<x-report-condition-badges :report="$report" />', [
        'report' => $notFoundReport,
    ]);

    expect($notFoundBadges)
        ->toContain('class="inline-flex items-center rounded-sm px-2.5 py-0.5 text-xs font-medium border border-red-200 bg-red-50 text-red-900">Water source not found</span>')
        ->not->toContain('bg-red-600 text-white');
});

test('problem flags are included in API JSON CSV and XLSX source exports', function () {
    $spring = Spring::factory()->create();
    $report = Report::factory()->create([
        'spring_id' => $spring->id,
        'access' => 'limited',
        'littered' => true,
        'ruined' => true,
    ]);

    $resource = (new ExportedReportResource($report))->resolve();

    expect($resource)
        ->toMatchArray([
            'access' => 'limited',
            'littered' => true,
            'ruined' => true,
        ]);

    $springs = new Collection([$spring]);
    $json = (new JsonTransformer($springs))->transform();
    $jsonReport = $json[0]['reports'][0];

    expect($jsonReport)
        ->toMatchArray([
            'access' => 'limited',
            'littered' => true,
            'ruined' => true,
        ]);

    $csvTransformer = new CsvTransformer($springs);
    $csvReport = $csvTransformer->transformReports()[0];

    expect($csvTransformer->getHeadersForReports())
        ->toBe(['id', 'spring_id', 'user', 'user_id', 'created_at', 'visited_at', 'state', 'quality', 'access', 'littered', 'ruined', 'comment']);
    expect($csvReport)
        ->toMatchArray([
            'access' => 'limited',
            'littered' => 'yes',
            'ruined' => 'yes',
        ]);
});

test('problem flags are included in Telegram and GPX report summaries', function () {
    $report = Report::factory()->create([
        'state' => 'dripping',
        'quality' => 'uncertain',
        'access' => 'no',
        'littered' => true,
        'ruined' => true,
    ])->load(['photos', 'spring', 'user']);

    $telegramText = (new ReportNotification($report))->toTelegram()->toArray()['text'];

    expect($telegramText)
        ->toContain('Very little water')
        ->toContain('questionable water')
        ->toContain('no access')
        ->toContain('littered')
        ->toContain('ruined');

    $gpxDescription = EnrichGPX::getEnrichedDescriptionForId($report->spring_id);

    expect($gpxDescription)
        ->toContain('[Very Little Water]')
        ->toContain('[Questionable Water]')
        ->toContain('[No Access]')
        ->toContain('[Littered]')
        ->toContain('[Ruined]');
});

test('report admin table exposes all problem fields', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Report::factory()->create([
        'state' => ReportState::Running,
        'quality' => ReportQuality::Uncertain,
        'access' => ReportAccess::Limited,
    ]);

    Livewire::test(ListReports::class)
        ->assertTableColumnExists('access')
        ->assertTableColumnExists('littered')
        ->assertTableColumnExists('ruined')
        ->assertSee('Has water')
        ->assertSee('Questionable water')
        ->assertSee('Limited access');
});
