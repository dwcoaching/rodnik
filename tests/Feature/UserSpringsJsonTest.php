<?php

declare(strict_types=1);

use App\Enums\ReportQuality;
use App\Enums\ReportState;
use App\Models\Report;
use App\Models\Spring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user springs json keeps hidden springs and uses visible reports', function () {
    $user = User::factory()->create();
    $hiddenSpring = Spring::factory()->create(['hidden_at' => now()]);

    Report::factory()->for($hiddenSpring)->for($user)->create([
        'state' => ReportState::NotFound,
        'quality' => null,
    ]);
    Report::factory()->for($hiddenSpring)->create([
        'state' => ReportState::Running,
        'quality' => ReportQuality::Good,
        'hidden_at' => now(),
    ]);
    Report::factory()->for($hiddenSpring)->create([
        'state' => ReportState::Running,
        'quality' => ReportQuality::Good,
        'from_osm' => true,
    ]);

    $response = $this->getJson("/users/{$user->id}/springs.json")
        ->assertSuccessful();

    $feature = collect($response->json('features'))->firstWhere('id', $hiddenSpring->id);

    expect($feature)->not->toBeNull()
        ->and($feature['properties'])->toMatchArray([
            'hasReports' => 1,
            'score' => null,
            'notFound' => true,
        ])
        ->and($feature['properties'])->not->toHaveKey('hidden');
});
