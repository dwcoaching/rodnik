<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Track;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class MapPayload
{
    /** @param array<string, mixed> $attributes
     * @return array{track_id: ?int}
     */
    public function resolveTrack(array $attributes): array
    {
        $token = $attributes['track_token'] ?? null;
        if ($token !== null) {
            $track = Track::query()->where('token', $token)->lockForUpdate()->first(['id']);
            if ($track === null) {
                throw ValidationException::withMessages(['track_token' => [__('validation.exists', ['attribute' => 'track'])]]);
            }

            return ['track_id' => $track->id];
        }

        return ['track_id' => null];
    }

    /** @return array<string, mixed> */
    public function defaultState(): array
    {
        return [
            'version' => 1,
            'center' => [19.748, 49.213],
            'zoom' => 3,
            'sourceName' => 'osm',
            'filters' => array_fill_keys(['spring', 'water_well', 'water_tap', 'drinking_water', 'fountain', 'other'], true)
                + ['with_reports' => false, 'along' => false],
            'overlays' => ['stravaPublic' => false, 'osmTraces' => false],
            'page' => ['spring' => null, 'user' => null, 'location' => null],
            'fullscreen' => false,
            'minimized' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{title?: string, state: array<string, mixed>, track_token: ?string, version?: int}
     */
    public function validate(array $attributes, bool $updating = false, ?string $locale = null): array
    {
        if (isset($attributes['title']) && is_string($attributes['title'])) {
            $attributes['title'] = mb_trim($attributes['title']);
        }

        $serializedState = json_encode($attributes['state'] ?? null, depth: 32);

        if ($serializedState === false || mb_strlen($serializedState, '8bit') > 16384) {
            throw ValidationException::withMessages(['state' => [__('The map state is too large or invalid.')]]);
        }

        $rules = [
            'title' => $updating ? ['bail', 'sometimes', 'required', 'string', 'max:160'] : ['bail', 'required', 'string', 'max:160'],
            'track_token' => ['bail', 'present', 'nullable', 'string', 'regex:/\A[a-zA-Z0-9]{10}\z/', Rule::exists(Track::class, 'token')],
            'state' => ['bail', 'required', 'array:version,center,zoom,sourceName,filters,overlays,page,fullscreen,minimized'],
            'state.version' => ['required', 'integer:strict', Rule::in([1])],
            'state.center' => ['required', 'array', 'list', 'size:2'],
            'state.center.0' => ['required', 'numeric:strict', 'between:-180,180'],
            'state.center.1' => ['required', 'numeric:strict', 'between:-90,90'],
            'state.zoom' => ['required', 'numeric:strict', 'between:0,28'],
            'state.sourceName' => ['required', 'string', Rule::in(['osm', 'mapy', 'outdoors', 'openTopoMap', 'terrain', 'satellite'])],
            'state.filters' => ['required', 'array:spring,water_well,water_tap,drinking_water,fountain,other,with_reports,along'],
            'state.overlays' => ['required', 'array:stravaPublic,osmTraces'],
            'state.page' => ['required', 'array:spring,user,location'],
            'state.page.spring' => ['present', 'nullable', 'integer:strict', 'min:1'],
            'state.page.user' => ['present', 'nullable', 'integer:strict', 'min:1'],
            'state.page.location' => ['present', 'nullable', 'integer:strict', Rule::in([1])],
            'state.fullscreen' => ['required', 'boolean:strict'],
            'state.minimized' => ['required', 'boolean:strict'],
        ];

        foreach (['spring', 'water_well', 'water_tap', 'drinking_water', 'fountain', 'other', 'with_reports', 'along'] as $filter) {
            $rules['state.filters.'.$filter] = ['required', 'boolean:strict'];
        }

        foreach (['stravaPublic', 'osmTraces'] as $overlay) {
            $rules['state.overlays.'.$overlay] = ['required', 'boolean:strict'];
        }

        if ($updating) {
            $rules['version'] = ['required', 'integer:strict', 'between:1,4294967294'];
        }

        return Validator::make($attributes, $rules, ['title.required' => __('ui.maps.title_required', locale: $locale)])->validate();
    }
}
