<?php

declare(strict_types=1);

use App\Enums\ReportAccess;
use App\Enums\ReportQuality;
use App\Enums\ReportState;
use App\Models\Report;
use App\Models\Spring;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a report contributes the expected water score', function (array $tags, ?int $expectedScore) {
    $spring = Spring::factory()->create();

    $report = Report::factory()->for($spring)->create(array_merge([
        'state' => null,
        'quality' => null,
        'access' => null,
        'littered' => null,
        'ruined' => null,
    ], $tags));

    expect($report->getWaterScore())->toBe($expectedScore)
        ->and($spring->fresh()->getWaterScore())
        ->toBe($expectedScore === null ? null : (float) $expectedScore);
})->with([
    'no tags' => [[], null],
    'good water' => [['quality' => ReportQuality::Good], 1],
    'poor water' => [['quality' => ReportQuality::Bad], -1],
    'dry' => [['state' => ReportState::Dry], -1],
    'not found' => [['state' => ReportState::NotFound], null],
    'not found with poor water' => [[
        'state' => ReportState::NotFound,
        'quality' => ReportQuality::Bad,
    ], null],
    'no access' => [['access' => ReportAccess::No], -1],
    'good water with no access' => [[
        'quality' => ReportQuality::Good,
        'access' => ReportAccess::No,
    ], -1],
    'good water with limited access' => [[
        'quality' => ReportQuality::Good,
        'access' => ReportAccess::Limited,
    ], 0],
    'poor water with limited access' => [[
        'quality' => ReportQuality::Bad,
        'access' => ReportAccess::Limited,
    ], -1],
    'dry with limited access' => [[
        'state' => ReportState::Dry,
        'access' => ReportAccess::Limited,
    ], -1],
    'has water without quality' => [['state' => ReportState::Running], 0],
    'very little water without quality' => [['state' => ReportState::Dripping], 0],
    'questionable water' => [['quality' => ReportQuality::Uncertain], 0],
    'limited access' => [['access' => ReportAccess::Limited], 0],
    'littered' => [['littered' => true], 0],
    'ruined' => [['ruined' => true], 0],
]);

test('water score averages non-null report scores', function () {
    $spring = Spring::factory()->create();

    foreach ([
        ['quality' => ReportQuality::Good],
        ['quality' => ReportQuality::Good],
        ['quality' => ReportQuality::Bad],
        ['state' => ReportState::Running],
        [],
        ['state' => ReportState::NotFound],
    ] as $tags) {
        Report::factory()->for($spring)->create(array_merge([
            'state' => null,
            'quality' => null,
            'access' => null,
            'littered' => null,
            'ruined' => null,
        ], $tags));
    }

    $this->assertEqualsWithDelta(0.25, $spring->fresh()->getWaterScore(), PHP_FLOAT_EPSILON);
});

test('water score ignores hidden and osm reports', function () {
    $spring = Spring::factory()->create();

    Report::factory()->for($spring)->create([
        'state' => null,
        'quality' => ReportQuality::Good,
    ]);
    Report::factory()->for($spring)->create([
        'state' => null,
        'quality' => ReportQuality::Bad,
        'hidden_at' => now(),
    ]);
    Report::factory()->for($spring)->create([
        'state' => null,
        'quality' => ReportQuality::Bad,
        'from_osm' => true,
    ]);

    expect($spring->fresh()->getWaterScore())->toBe(1.0);
});

test('good quality confirms water without requiring a running state', function () {
    $spring = Spring::factory()->create();

    Report::factory()->for($spring)->create([
        'state' => null,
        'quality' => ReportQuality::Good,
    ]);

    expect($spring->fresh()->waterConfirmed())->toBeTrue();
});

test('confirmation is decided by the majority of visible quality reports', function () {
    $spring = Spring::factory()->create();

    foreach ([
        ['quality' => ReportQuality::Good, 'state' => ReportState::Dry],
        ['quality' => ReportQuality::Good, 'state' => null],
        ['quality' => ReportQuality::Bad, 'state' => ReportState::Running],
        ['quality' => null, 'state' => ReportState::Dry],
    ] as $attributes) {
        Report::factory()->for($spring)->create($attributes);
    }

    Report::factory()->for($spring)->create([
        'quality' => ReportQuality::Bad,
        'state' => ReportState::Dry,
        'hidden_at' => now(),
    ]);

    expect($spring->fresh()->waterConfirmed())->toBeTrue();
});

test('backend decides whether a spring is not found from visible reports', function () {
    $notFound = Spring::factory()->create();
    Report::factory()->for($notFound)->create([
        'state' => ReportState::NotFound,
        'quality' => null,
    ]);
    Report::factory()->for($notFound)->create([
        'state' => null,
        'quality' => null,
    ]);
    Report::factory()->for($notFound)->create([
        'state' => ReportState::Running,
        'quality' => null,
        'hidden_at' => now(),
    ]);
    Report::factory()->for($notFound)->create([
        'state' => ReportState::Running,
        'quality' => null,
        'from_osm' => true,
    ]);

    $found = Spring::factory()->create();
    Report::factory()->for($found)->create([
        'state' => ReportState::NotFound,
        'quality' => null,
    ]);
    Report::factory()->for($found)->create([
        'state' => ReportState::Dry,
        'quality' => null,
    ]);

    $invisibleNotFound = Spring::factory()->create();
    Report::factory()->for($invisibleNotFound)->create([
        'state' => ReportState::NotFound,
        'quality' => null,
        'hidden_at' => now(),
    ]);

    expect($notFound->fresh()->isNotFound())->toBeTrue()
        ->and($found->fresh()->isNotFound())->toBeFalse()
        ->and($invisibleNotFound->fresh()->isNotFound())->toBeFalse();
});

test('water score can reach an exact map color boundary', function (array $votes, float $expectedScore) {
    $spring = Spring::factory()->create();

    foreach ($votes as $vote) {
        Report::factory()->for($spring)->create(array_merge([
            'state' => null,
            'quality' => null,
            'access' => null,
            'littered' => null,
            'ruined' => null,
        ], $vote));
    }

    $this->assertEqualsWithDelta($expectedScore, $spring->fresh()->getWaterScore(), PHP_FLOAT_EPSILON);
})->with([
    'green boundary' => [[
        ['quality' => ReportQuality::Good],
        ['quality' => ReportQuality::Good],
        ['quality' => ReportQuality::Good],
        ['quality' => ReportQuality::Bad],
        ['state' => ReportState::Running],
    ], 0.4],
    'red boundary' => [[
        ['quality' => ReportQuality::Good],
        ['quality' => ReportQuality::Bad],
        ['quality' => ReportQuality::Bad],
        ['quality' => ReportQuality::Bad],
        ['state' => ReportState::Running],
    ], -0.4],
]);

test('map marker styles use the shared score classifier and zoom-specific not found handling', function () {
    expect(file_get_contents(resource_path('js/utils/classifyScore.js')))
        ->toContain('WATER_SCORE_THRESHOLD = '.Spring::WATER_SCORE_THRESHOLD)
        ->toContain('score >= WATER_SCORE_THRESHOLD')
        ->toContain('score <= -WATER_SCORE_THRESHOLD');

    foreach (['final.js', 'approximated.js', 'distant.js'] as $style) {
        $contents = file_get_contents(resource_path('js/styles/'.$style));

        expect($contents)
            ->toContain("feature.get('notFound')")
            ->toContain("classifyScore(feature.get('score'))")
            ->not->toContain('0.4');

        expect(mb_strpos($contents, "feature.get('notFound')"))
            ->toBeLessThan(mb_strpos($contents, "classifyScore(feature.get('score'))"));
    }

    foreach (['approximated.js', 'distant.js'] as $style) {
        $contents = file_get_contents(resource_path('js/styles/'.$style));

        expect($contents)
            ->toContain("if (feature.get('notFound')) {\n        return hiddenStyle;")
            ->not->toContain('notFoundStyle');
    }

    expect(file_get_contents(resource_path('js/styles/final.js')))
        ->toContain("if (feature.get('notFound')) {\n        return notFoundStyle;");
});
