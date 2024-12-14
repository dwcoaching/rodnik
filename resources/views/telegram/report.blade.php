@if ($report->user_id)
<b>{{ $report->user->name }}, {{ Date::parse($report->visited_at)->format('j F Y') }}</b>
@else
<b>Anonymous, {{ Date::parse($report->visited_at)->format('j F Y') }}</b>
@endif
{{ $report->spring->name ? $report->spring->name : $report->spring->type }}
@if ($report->new_name)
Name changed: {{ $report->old_name }} → {{ $report->new_name }}
@endif
@if ($report->new_type)
Type changed: {{ $report->old_type }} → {{ $report->new_type }}
@endif
@if ($report->new_latitude || $report->new_longitude)
Coordinates changed: {{ $report->old_latitude }}, {{ $report->old_longitude }} → {{ $report->new_latitude }}, {{ $report->new_longitude }}
@endif
@if (count($tags))

{{ implode(', ', $tags) }}.
@endif
@if ($report->comment)

{{ $report->comment }}
@endif

@if ($photoCount < 2)
{{ route('duo', ['s' => $report->spring->id]) }}
@else
{{ decline_number($photoCount - 1, ['more photo', 'more photos', 'more photos']) }} at {{ route('duo', ['s' => $report->spring->id]) }}
@endif
