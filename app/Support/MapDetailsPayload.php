<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Map;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class MapDetailsPayload
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{title?: string, slug?: ?string, version?: int}
     */
    public function validate(array $attributes, ?Map $map = null, ?string $locale = null): array
    {
        foreach (['title', 'slug'] as $key) {
            if (isset($attributes[$key]) && is_string($attributes[$key])) {
                $attributes[$key] = mb_trim($attributes[$key]);
            }
        }

        if (array_key_exists('slug', $attributes) && is_string($attributes['slug'])) {
            $attributes['slug'] = $attributes['slug'] === '' ? null : mb_strtolower($attributes['slug']);
        }
        $rules = [
            'title' => $map !== null ? ['bail', 'sometimes', 'required', 'string', 'max:160'] : ['bail', 'required', 'string', 'max:160'],
            'slug' => $this->slugRules($map),
        ];
        if ($map !== null) {
            $rules['version'] = ['required', 'integer:strict', 'between:1,4294967294'];
        }

        return Validator::make($attributes, $rules, $this->messages($locale))->validate();
    }

    public function validateSlug(mixed $slug, ?Map $map = null): void
    {
        if (is_string($slug)) {
            $slug = mb_trim($slug);
            $slug = $slug === '' ? null : mb_strtolower($slug);
        }

        Validator::make(['slug' => $slug], ['slug' => $this->slugRules($map)], $this->messages())->validate();
    }

    /** @return array<string, string> */
    private function messages(?string $locale = null): array
    {
        return [
            'title.required' => __('ui.maps.title_required', locale: $locale),
            'slug.unique' => __('ui.maps.link_unavailable', locale: $locale),
            ...array_fill_keys(['slug.string', 'slug.min', 'slug.max', 'slug.regex'], __('ui.maps.link_invalid', locale: $locale)),
        ];
    }

    /** @return list<mixed> */
    private function slugRules(?Map $map): array
    {
        $unique = Rule::unique(Map::class, 'slug');
        if ($map !== null) {
            $unique->ignore($map);
        }

        return ['bail', 'sometimes', 'nullable', 'string', 'min:3', 'max:80', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $unique];
    }
}
