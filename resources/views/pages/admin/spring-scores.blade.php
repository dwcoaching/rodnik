<?php

use App\Models\Report;
use App\Models\Spring;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\View\View;

use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('docs.admin.spring-scores');

render(function (View $view): View {
    $springs = Spring::query()
        ->select(['id', 'name', 'type', 'hidden_at', 'redirect_to_spring_id'])
        ->whereHas('visibleReports')
        ->withCount('visibleReports')
        ->with([
            'visibleReports' => function (HasMany $query): void {
                $query->select(['id', 'spring_id', 'visited_at', ...Report::CONDITION_COLUMNS])
                    ->latest('visited_at')
                    ->latest('id');
            },
        ])
        ->orderByDesc('visible_reports_count')
        ->orderBy('id')
        ->paginate(100);

    return $view->with('springs', $springs);
});

?>

@extends('folio.index')

@section('content')
    <div class="min-w-0 max-w-full">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-gray-950">Spring scores</h1>
                <p class="mt-2 max-w-3xl text-sm text-gray-600">
                    Temporary scoring reference. Scores use the current map formula; report tags include visible user reports only.
                </p>
            </div>

            <div class="text-sm text-gray-500">
                {{ number_format($springs->total()) }} springs · 100 per page
            </div>
        </div>

        <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="divide-y divide-gray-200">
                @forelse ($springs as $spring)
                    @php
                        $score = $spring->getWaterScore();
                    @endphp

                    <article class="grid gap-4 p-4 lg:grid-cols-[minmax(12rem,20rem)_minmax(0,1fr)] lg:p-5">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <a
                                    href="{{ route('springs.show', ['springId' => $spring->id]) }}"
                                    class="font-semibold text-blue-700 hover:text-blue-900 hover:underline"
                                >
                                    #{{ $spring->id }} {{ $spring->name ?: 'Unnamed spring' }}
                                </a>

                                <span
                                    data-test="spring-score-{{ $spring->id }}"
                                    @class([
                                        'inline-flex min-w-16 justify-center rounded-full px-2.5 py-1 text-xs font-bold tabular-nums',
                                        'bg-green-100 text-green-900' => $score !== null && $score >= Spring::WATER_SCORE_THRESHOLD,
                                        'bg-red-100 text-red-900' => $score !== null && $score <= -Spring::WATER_SCORE_THRESHOLD,
                                        'bg-yellow-100 text-yellow-900' => $score === null || ($score > -Spring::WATER_SCORE_THRESHOLD && $score < Spring::WATER_SCORE_THRESHOLD),
                                    ])
                                >
                                    Score {{ $score === null ? 'null' : round($score, 2) }}
                                </span>
                            </div>

                            <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500">
                                <span>{{ $spring->type }}</span>
                                <span>{{ $spring->visibleReports->count() }} visible {{ Str::plural('report', $spring->visibleReports->count()) }}</span>
                                @if ($spring->hidden_at)
                                    <span class="font-medium text-red-700">Hidden spring</span>
                                @endif
                                @if ($spring->redirect_to_spring_id)
                                    <span class="font-medium text-amber-700">Merged into #{{ $spring->redirect_to_spring_id }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="min-w-0">
                            @forelse ($spring->visibleReports as $report)
                                @php
                                    $contribution = $report->getWaterScore();
                                    $contributionLabel = match ($contribution) {
                                        1 => '+1',
                                        -1 => '-1',
                                        0 => '0',
                                        null => 'null',
                                    };
                                @endphp

                                <div class="flex flex-col gap-2 py-2 first:pt-0 last:pb-0 sm:flex-row sm:items-start">
                                    <div class="shrink-0 text-xs text-gray-500 sm:w-28">
                                        Report #{{ $report->id }}
                                        @if ($report->visited_at)
                                            <div>{{ $report->visited_at->format('Y-m-d') }}</div>
                                        @endif
                                        <span
                                            data-test="report-contribution-{{ $report->id }}"
                                            @class([
                                                'mt-1 inline-flex rounded-full px-2 py-0.5 font-bold tabular-nums',
                                                'bg-green-100 text-green-900' => $contribution === 1,
                                                'bg-red-100 text-red-900' => $contribution === -1,
                                                'bg-yellow-100 text-yellow-900' => $contribution === 0,
                                                'bg-gray-100 text-gray-600' => $contribution === null,
                                            ])
                                        >
                                            Score: {{ $contributionLabel }}
                                        </span>
                                    </div>

                                    @if ($report->hasConditionSignals())
                                        <x-report-condition-badges :report="$report" />
                                    @else
                                        <span class="text-xs italic text-gray-400">No report tags</span>
                                    @endif
                                </div>
                            @empty
                                <span class="text-sm italic text-gray-400">No visible reports</span>
                            @endforelse
                        </div>
                    </article>
                @empty
                    <div class="p-8 text-center text-sm text-gray-500">No springs found.</div>
                @endforelse
            </div>
        </div>

        <div class="mt-6">
            {{ $springs->links() }}
        </div>
    </div>
@endsection
