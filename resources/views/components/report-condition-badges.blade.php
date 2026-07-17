<div {{ $attributes->class(['flex flex-wrap gap-1']) }}>
    @foreach ([$report->state, $report->quality] as $condition)
        @if ($condition !== null)
            <span @class([
                'inline-flex items-center rounded-sm px-2.5 py-0.5 text-xs font-medium',
                'border border-green-200 bg-green-50 text-green-900' => $condition->getColor() === 'success',
                'bg-yellow-400 text-black' => $condition->getColor() === 'warning',
                'border border-red-200 bg-red-50 text-red-900' => $condition->getColor() === 'danger',
            ])>{{ $condition->getLabel() }}</span>
        @endif
    @endforeach

    @if ($report->access_limited)
        <span class="inline-flex items-center rounded-sm border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-900">Access limited</span>
    @endif

    @if ($report->littered)
        <span class="inline-flex items-center rounded-sm border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-900">Littered</span>
    @endif

    @if ($report->broken)
        <span class="inline-flex items-center rounded-sm border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-900">Broken</span>
    @endif
</div>
