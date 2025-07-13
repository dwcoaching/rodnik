<b>New Water Source Edit:</b>
@if ($revision->user_id)
{{ \Carbon\Carbon::parse($revision->created_at) }}, {{ $revision->user->name }}
@endif

<b>Changes:</b>
@if (!
    (
        $revision->old_latitude === $revision->new_latitude
        && $revision->old_longitude === $revision->old_longitude
    )
)
Location: {{ $revision->old_latitude . ', ' . $revision->old_longitude }} → {{ $revision->new_latitude . ', ' . $revision->new_longitude }}
@endif
@if ($revision->old_name !== $revision->new_name)
Name: {{ $revision->old_name }} → {{ $revision->new_name }}
@endif
@if ($revision->old_type !== $revision->new_type)
Type: {{ $revision->old_type }} → {{ $revision->new_type }}
@endif
@if ($revision->old_intermittent !== $revision->new_intermittent)
Intermittent: {{ $revision->old_intermittent }} → {{ $revision->new_intermittent }}
@endif

{{ duo_route(['spring' => $revision->spring_id]) }}
