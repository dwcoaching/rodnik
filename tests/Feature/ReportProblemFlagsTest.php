<?php

declare(strict_types=1);

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

test('report condition values use enum and boolean casts', function () {
    $report = Report::factory()->create([
        'state' => ReportState::Running,
        'quality' => ReportQuality::Good,
        'access_limited' => true,
    ]);

    expect($report->state)->toBe(ReportState::Running)
        ->and($report->quality)->toBe(ReportQuality::Good)
        ->and($report->access_limited)->toBeTrue()
        ->and($report->getRawOriginal('state'))->toBe(ReportState::Running->value)
        ->and($report->getRawOriginal('quality'))->toBe(ReportQuality::Good->value)
        ->and($report->getRawOriginal('access_limited'))->toBeTrue();
});

test('report history renders enum titles', function () {
    $user = User::factory()->create();
    $report = Report::factory()->create([
        'state' => ReportState::Running,
        'quality' => ReportQuality::Uncertain,
        'access_limited' => true,
    ]);

    $this->actingAs($user)
        ->get(route('springs.history', $report->spring))
        ->assertSuccessful()
        ->assertSee('State: Has water')
        ->assertSee('Quality: Questionable water')
        ->assertSee('Access limited');
});

test('problem badges render on report details and report teasers', function () {
    $report = Report::factory()->create([
        'state' => 'running',
        'quality' => 'uncertain',
        'access_limited' => true,
        'littered' => true,
        'broken' => true,
    ]);

    Livewire::test(ShowReport::class, ['report' => $report])
        ->assertSee('Questionable water')
        ->assertSee('Access limited')
        ->assertSee('Littered')
        ->assertSee('Broken');

    $teaser = Blade::render('<x-last-reports.teaser :report="$report" />', [
        'report' => $report->load(['spring', 'user', 'photos']),
    ]);

    expect($teaser)
        ->toContain('Has water')
        ->toContain('Questionable water')
        ->toContain('class="report-condition-badge report-condition-badge--warning">Access limited</span>')
        ->toContain('class="report-condition-badge report-condition-badge--warning">Littered</span>')
        ->toContain('class="report-condition-badge report-condition-badge--warning">Questionable water</span>')
        ->toContain('Broken')
        ->toContain('class="report-condition-badge report-condition-badge--warning">Broken</span>');
});

test('problem badges render access limited and omit unreported problems', function () {
    $successReport = Report::factory()->make([
        'state' => 'running',
        'quality' => 'good',
        'access_limited' => null,
        'littered' => null,
        'broken' => null,
    ]);

    $successBadges = Blade::render('<x-report-condition-badges :report="$report" />', [
        'report' => $successReport,
    ]);

    expect($successBadges)
        ->toContain('class="report-condition-badge report-condition-badge--success">Has water</span>')
        ->toContain('class="report-condition-badge report-condition-badge--success">Good water</span>')
        ->not->toContain('border-green-200');

    $accessLimitedReport = Report::factory()->make([
        'state' => null,
        'quality' => null,
        'access_limited' => true,
        'littered' => null,
        'broken' => null,
    ]);

    $accessLimitedBadges = Blade::render('<x-report-condition-badges :report="$report" />', [
        'report' => $accessLimitedReport,
    ]);

    expect($accessLimitedBadges)
        ->toContain('Access limited')
        ->toContain('class="report-condition-badge report-condition-badge--warning">Access limited</span>')
        ->not->toContain('Littered')
        ->not->toContain('Broken');

    $emptyReport = Report::factory()->make([
        'state' => null,
        'quality' => null,
        'access_limited' => null,
        'littered' => null,
        'broken' => null,
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
        'access_limited' => null,
        'littered' => null,
        'broken' => null,
    ]);

    $dryAndPoorBadges = Blade::render('<x-report-condition-badges :report="$report" />', [
        'report' => $dryAndPoorReport,
    ]);

    expect($dryAndPoorBadges)
        ->toContain('class="report-condition-badge report-condition-badge--danger">Dry</span>')
        ->toContain('class="report-condition-badge report-condition-badge--danger">Poor water</span>')
        ->not->toContain('border-red-200');

    $notFoundReport = Report::factory()->make([
        'state' => 'notfound',
        'quality' => null,
        'access_limited' => null,
        'littered' => null,
        'broken' => null,
    ]);

    $notFoundBadges = Blade::render('<x-report-condition-badges :report="$report" />', [
        'report' => $notFoundReport,
    ]);

    expect($notFoundBadges)
        ->toContain('class="report-condition-badge report-condition-badge--danger">Water source not found</span>')
        ->not->toContain('border-red-200');
});

test('all warning conditions use the fixed warning style', function () {
    $report = Report::factory()->make([
        'state' => 'dripping',
        'quality' => 'uncertain',
    ]);

    $badges = Blade::render('<x-report-condition-badges :report="$report" />', [
        'report' => $report,
    ]);

    expect($badges)
        ->toContain('class="report-condition-badge report-condition-badge--warning">Very little water</span>')
        ->toContain('class="report-condition-badge report-condition-badge--warning">Questionable water</span>');
});

test('problem flags are included in API JSON CSV and XLSX source exports', function () {
    $spring = Spring::factory()->create();
    $report = Report::factory()->create([
        'spring_id' => $spring->id,
        'access_limited' => true,
        'littered' => true,
        'broken' => true,
    ]);

    $resource = (new ExportedReportResource($report))->resolve();

    expect($resource)
        ->toMatchArray([
            'access_limited' => true,
            'littered' => true,
            'broken' => true,
        ]);

    $springs = new Collection([$spring]);
    $json = (new JsonTransformer($springs))->transform();
    $jsonReport = $json[0]['reports'][0];

    expect($jsonReport)
        ->toMatchArray([
            'access_limited' => true,
            'littered' => true,
            'broken' => true,
        ]);

    $csvTransformer = new CsvTransformer($springs);
    $csvReport = $csvTransformer->transformReports()[0];

    expect($csvTransformer->getHeadersForReports())
        ->toBe(['id', 'spring_id', 'user', 'user_id', 'created_at', 'visited_at', 'state', 'quality', 'access_limited', 'littered', 'broken', 'comment']);
    expect($csvReport)
        ->toMatchArray([
            'access_limited' => 'yes',
            'littered' => 'yes',
            'broken' => 'yes',
        ]);
});

test('problem flags are included in Telegram and GPX report summaries', function () {
    $report = Report::factory()->create([
        'state' => 'dripping',
        'quality' => 'uncertain',
        'access_limited' => true,
        'littered' => true,
        'broken' => true,
    ])->load(['photos', 'spring', 'user']);

    $telegramText = (new ReportNotification($report))->toTelegram()->toArray()['text'];

    expect($telegramText)
        ->toContain('Very little water')
        ->toContain('questionable water')
        ->toContain('access limited')
        ->toContain('littered')
        ->toContain('broken');

    $gpxDescription = EnrichGPX::getEnrichedDescriptionForId($report->spring_id);

    expect($gpxDescription)
        ->toContain('[Very Little Water]')
        ->toContain('[Questionable Water]')
        ->toContain('[Access Limited]')
        ->toContain('[Littered]')
        ->toContain('[Broken]');
});

test('report admin table exposes all problem fields', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Report::factory()->create([
        'state' => ReportState::Running,
        'quality' => ReportQuality::Uncertain,
        'access_limited' => true,
    ]);

    Livewire::test(ListReports::class)
        ->assertTableColumnExists('access_limited')
        ->assertTableColumnExists('littered')
        ->assertTableColumnExists('broken')
        ->assertSee('Has water')
        ->assertSee('Questionable water')
        ->assertSee('Access limited');
});
