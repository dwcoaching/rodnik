@if ($report->user_id)
<b>{{ $report->user->name }}, {{ Date::parse($report->visited_at)->format('j F Y') }}</b>
@else
<b>Анонимно, {{ Date::parse($report->visited_at)->format('j F Y') }}</b>
@endif
{{ $report->spring->name ? $report->spring->name : $report->spring->type }} {{--<a href="{{ route('springs.show', $report->spring) }}">rodnik.today/{{ $report->spring->id }}</a>--}}
@if ($report->new_name)
Изменено название: {{ $report->old_name }} → {{ $report->new_name }}
@endif
@if ($report->new_type)
Изменен тип: {{ $report->old_type }} → {{ $report->new_type }}
@endif
@if ($report->new_latitude || $report->new_longitude)
Изменены координаты: {{ $report->old_latitude }}, {{ $report->old_longitude }} → {{ $report->new_latitude }}, {{ $report->new_longitude }}
@endif
@if (count($tags))

{{ implode(', ', $tags) }}.
@endif
@if ($report->comment)

{{ $report->comment }}
@endif

@if ($photoCount < 2)
{{ route('springs.show', $report->spring->id) }}
@else
Еще {{ decline_number($photoCount - 1, ['фотография', 'фотографии', 'фотографий']) }} на {{ route('springs.show', $report->spring->id) }}
@endif
