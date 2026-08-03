<div {{ $attributes->class(['flex flex-wrap gap-1']) }}>
    @foreach ([$report->state, $report->quality] as $condition)
        @if ($condition !== null)
            <span @class([
                'report-condition-badge',
                'report-condition-badge--success' => $condition->getColor() === 'success',
                'report-condition-badge--warning' => $condition->getColor() === 'warning',
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
        <span class="report-condition-badge report-condition-badge--warning">{{ __('ui.report.badges.broken') }}</span>
    @endif
</div>
