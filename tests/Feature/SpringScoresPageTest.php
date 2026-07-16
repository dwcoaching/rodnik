<?php

declare(strict_types=1);

use App\Enums\ReportAccess;
use App\Enums\ReportQuality;
use App\Enums\ReportState;
use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('spring scores page displays the current score and visible report tags', function () {
    $spring = Spring::factory()->create(['name' => 'Scoring reference spring']);
    Spring::factory()->create(['name' => 'Spring without reports']);

    Report::factory()->for($spring)->create([
        'state' => ReportState::Running,
        'quality' => ReportQuality::Good,
        'access' => ReportAccess::Limited,
        'littered' => true,
    ]);

    Report::factory()->for($spring)->create([
        'state' => ReportState::NotFound,
        'quality' => null,
        'access' => null,
        'hidden_at' => now(),
    ]);

    $expectedScore = $spring->fresh()
        ->load(['reports' => fn ($query) => $query->visible()])
        ->getWaterScore();

    $this->get(route('docs.admin.spring-scores'))
        ->assertSuccessful()
        ->assertSee('Scoring reference spring')
        ->assertSeeHtml('data-test="spring-score-'.$spring->id.'"')
        ->assertSee('Score '.$expectedScore)
        ->assertSee('Has water')
        ->assertSee('Good water')
        ->assertSee('Limited access')
        ->assertSee('Littered')
        ->assertDontSee('Spring without reports')
        ->assertDontSee('Water source not found');
});

test('spring scores page sorts springs by visible report count descending', function () {
    $springWithOneReport = Spring::factory()->create(['name' => 'One report spring']);
    $springWithThreeReports = Spring::factory()->create(['name' => 'Three reports spring']);
    $springWithTwoReports = Spring::factory()->create(['name' => 'Two reports spring']);

    Report::factory()->for($springWithOneReport)->create();
    Report::factory()->count(3)->for($springWithThreeReports)->create();
    Report::factory()->count(2)->for($springWithTwoReports)->create();

    $this->get(route('docs.admin.spring-scores'))
        ->assertSuccessful()
        ->assertSeeInOrder([
            'Three reports spring',
            'Two reports spring',
            'One report spring',
        ]);
});

test('spring scores page displays each report contribution', function () {
    $spring = Spring::factory()->create();

    $positiveReport = Report::factory()->for($spring)->create([
        'state' => null,
        'quality' => ReportQuality::Good,
        'access' => null,
        'littered' => null,
        'ruined' => null,
    ]);
    $negativeReport = Report::factory()->for($spring)->create([
        'state' => null,
        'quality' => ReportQuality::Bad,
        'access' => null,
        'littered' => null,
        'ruined' => null,
    ]);
    $neutralReport = Report::factory()->for($spring)->create([
        'state' => ReportState::Running,
        'quality' => null,
        'access' => null,
        'littered' => null,
        'ruined' => null,
    ]);
    $nullReport = Report::factory()->for($spring)->create([
        'state' => null,
        'quality' => null,
        'access' => null,
        'littered' => null,
        'ruined' => null,
    ]);

    $this->get(route('docs.admin.spring-scores'))
        ->assertSuccessful()
        ->assertSeeHtml('data-test="report-contribution-'.$positiveReport->id.'"')
        ->assertSee('Score: +1')
        ->assertSeeHtml('data-test="report-contribution-'.$negativeReport->id.'"')
        ->assertSee('Score: -1')
        ->assertSeeHtml('data-test="report-contribution-'.$neutralReport->id.'"')
        ->assertSee('Score: 0')
        ->assertSeeHtml('data-test="report-contribution-'.$nullReport->id.'"')
        ->assertSee('Score: null');
});

test('spring scores page colors score badges using the water score threshold', function () {
    $goodSpring = Spring::factory()->create();
    $badSpring = Spring::factory()->create();
    $uncertainSpring = Spring::factory()->create();

    Report::factory()->for($goodSpring)->create([
        'state' => null,
        'quality' => ReportQuality::Good,
        'access' => null,
        'littered' => null,
        'ruined' => null,
    ]);
    Report::factory()->for($badSpring)->create([
        'state' => null,
        'quality' => ReportQuality::Bad,
        'access' => null,
        'littered' => null,
        'ruined' => null,
    ]);
    Report::factory()->for($uncertainSpring)->create([
        'state' => ReportState::Running,
        'quality' => null,
        'access' => null,
        'littered' => null,
        'ruined' => null,
    ]);

    $html = $this->get(route('docs.admin.spring-scores'))
        ->assertSuccessful()
        ->content();

    $badgeClasses = function (Spring $spring) use ($html): string {
        preg_match('/data-test="spring-score-'.$spring->id.'"\s+class="([^"]*)"/', $html, $matches);

        return $matches[1] ?? '';
    };

    expect($badgeClasses($goodSpring))->toContain('bg-green-100');
    expect($badgeClasses($badSpring))->toContain('bg-red-100');
    expect($badgeClasses($uncertainSpring))->toContain('bg-yellow-100');
});

test('spring scores page paginates springs one hundred at a time', function () {
    $springs = collect([
        Spring::factory()->create(['name' => 'First scoring page marker']),
    ])->concat(Spring::factory()->count(99)->create())->push(
        Spring::factory()->create(['name' => 'Second scoring page marker']),
    );

    Report::factory()
        ->count($springs->count())
        ->recycle(User::factory()->create())
        ->sequence(...$springs->map(fn (Spring $spring): array => [
            'spring_id' => $spring->id,
        ])->all())
        ->create();

    $this->get(route('docs.admin.spring-scores'))
        ->assertSuccessful()
        ->assertSee('First scoring page marker')
        ->assertDontSee('Second scoring page marker');

    $this->get(route('docs.admin.spring-scores', ['page' => 2]))
        ->assertSuccessful()
        ->assertSee('Second scoring page marker')
        ->assertDontSee('First scoring page marker');
});
