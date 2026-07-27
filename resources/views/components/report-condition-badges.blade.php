<div {{ $attributes->class(['flex flex-wrap gap-1']) }}>
    @foreach ([$report->state, $report->quality] as $condition)
        @if ($condition !== null)
            <span @class([
                'report-condition-badge',
                'report-condition-badge--success' => $condition->getColor() === 'success',
                'border-amber-200 bg-amber-50 text-amber-900' => $condition->getColor() === 'warning',
                'report-condition-badge--danger' => $condition->getColor() === 'danger',
            ])>{{ __('ui.report.conditions.' . $condition->value) }}</span>
        @endif
    @endforeach

    @if ($report->access_limited)
        <span class="report-condition-badge report-condition-badge--warning">{{ __('ui.report.badges.access_limited') }}</span>
    @endif

    @if ($report->littered)
        <span class="report-condition-badge report-condition-badge--warning">{{ __('ui.report.badges.littered') }}</span>
    @endif

    @if ($report->broken)
        <span class="inline-flex items-center rounded-sm border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-900">{{ __('ui.report.badges.broken') }}</span>
    @endif
</div>
