<div {{ $attributes->class(['flex flex-wrap gap-1']) }}>
    @foreach ([$report->state, $report->quality, $report->access] as $condition)
        @if ($condition !== null)
            <span @class([
                'inline-flex items-center rounded-sm px-2.5 py-0.5 text-xs font-medium',
                'border border-green-200 bg-green-50 text-green-900' => $condition->getColor() === 'success',
                'bg-yellow-400 text-black' => $condition->getColor() === 'warning' && ! ($condition instanceof \App\Enums\ReportAccess),
                'border border-red-200 bg-red-50 text-red-900' => $condition->getColor() === 'danger',
                'border border-amber-200 bg-amber-50 text-amber-900' => $condition === \App\Enums\ReportAccess::Limited,
            ])>{{ $condition->getLabel() }}</span>
        @endif
    @endforeach

    @if ($report->littered)
        <span class="inline-flex items-center rounded-sm border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-900">Littered</span>
    @endif

    @if ($report->ruined)
        <span class="inline-flex items-center rounded-sm border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-900">Ruined</span>
    @endif
</div>
