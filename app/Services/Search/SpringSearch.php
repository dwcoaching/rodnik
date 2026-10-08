<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Report;
use App\Models\Spring;
use App\Support\DuoUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;

final class SpringSearch
{
    public const LIMIT = 5;

    private const MIN_LENGTH = 3;

    private const CANDIDATES = 60;

    private const VIEW_BOOST = ['inside' => 3.0, 'near' => 1.5];

    private const REPORTS_WEIGHT = 0.75;

    private const COLUMNS = ['id', 'name', 'type', 'latitude', 'longitude', 'nearest_city_id', 'region_id', 'country_id'];

    // Characters after which a word of a name starts, as the name matcher splits names.
    private const WORD_SEPARATORS = [' ', '-', '.', '(', '"', '«', '\'', '’', '/', ','];

    public function __construct(private readonly DuoUrl $duoUrl) {}

    /** The water source a number such as "123", "#123" or "№ 123" refers to. */
    public function numbered(string $query): ?Spring
    {
        // "№" is Shift+3 on the Russian layout and how Russian writes "number".
        return preg_match('/^[#№]?\s*([0-9]{1,10})$/u', $query, $matches) === 1 ? $this->find((int) $matches[1]) : null;
    }

    /** The visible water source an id leads to, after any merges. */
    public function find(int $id): ?Spring
    {
        $spring = Spring::query()->find($id);

        try {
            $spring = $spring?->redirect_to_spring_id ? $spring->finallyRedirectedTo() : $spring;
        } catch (RuntimeException) {
            return null;
        }

        return $spring === null || $spring->hidden_at !== null ? null : $spring;
    }

    /**
     * A water source found by its number or link.
     *
     * @return array<string, mixed>
     */
    public function item(Spring $spring, string $locale): array
    {
        $spring->loadMissing(['nearestCity:id,name,name_ru', 'geoCountry:id,name,name_ru']);
        $name = $spring->name ?: ($spring->type ? $this->typeLabel($spring->type, $locale) : __('ui.spring.no_name', [], $locale));

        return $this->result($spring, $locale, $name, SearchText::join([
            $this->placeName($spring->nearestCity, $locale),
            $this->placeName($spring->geoCountry, $locale),
        ]), 0);
    }

    /**
     * Visible water sources named like the term, best first.
     *
     * @param  array{west: float, south: float, east: float, north: float}|null  $view
     * @return list<array{bonus: float, item: array<string, mixed>}>
     */
    public function named(string $term, NameMatcher $matcher, string $locale, ?array $view): array
    {
        $against = SearchText::baseLength($term) >= self::MIN_LENGTH ? SearchText::fullTextQuery($term) : null;

        if ($against === null) {
            return [];
        }

        $area = $view === null ? null : MapArea::fromView($view);
        $near = $area?->expanded();
        $hits = [];

        foreach ($this->candidates($term, $against, $area) as $spring) {
            $bonus = $matcher->bonus($spring->name);

            if ($bonus === null) {
                continue;
            }

            $latitude = (float) $spring->latitude;
            $longitude = (float) $spring->longitude;
            $reports = (int) $spring->reports_count;
            $proximity = match (true) {
                $area === null || $near === null => 0.0,
                $area->contains($latitude, $longitude) => self::VIEW_BOOST['inside'],
                $near->contains($latitude, $longitude) => self::VIEW_BOOST['near'],
                default => 0.0,
            };
            $hits[] = [
                'spring' => $spring,
                'bonus' => $bonus,
                'reports' => $reports,
                'score' => $bonus + $proximity + self::REPORTS_WEIGHT * log10($reports + 1),
            ];
        }

        usort($hits, fn (array $a, array $b): int => [$b['score'], $a['spring']->name, $a['spring']->id]
            <=> [$a['score'], $b['spring']->name, $b['spring']->id]);
        $hits = array_slice($hits, 0, self::LIMIT);
        (new Collection(array_column($hits, 'spring')))
            ->load(['nearestCity:id,name,name_ru', 'geoRegion:id,name,name_ru', 'geoCountry:id,name,name_ru']);

        return array_map(function (array $hit) use ($locale): array {
            $spring = $hit['spring'];
            $type = $this->typeLabel($spring->type, $locale);
            // A source named just "Spring" needs no type next to it.
            $context = SearchText::join([SearchText::fold($type) === SearchText::fold($spring->name) ? null : $type, $this->place($spring, $locale)], ' · ');

            return ['bonus' => $hit['bonus'], 'item' => $this->result($spring, $locale, $spring->name, $context, $hit['reports'])];
        }, $hits);
    }

    /**
     * Common names such as "Родник" or "Brunnen" match thousands of sources. One pass over the matches ranks
     * them about as named() does, so the few fetched are the nearby, most reported and closest-named ones.
     *
     * @return Collection<int, Spring>
     */
    private function candidates(string $term, string $against, ?MapArea $area): Collection
    {
        $score = sprintf('%F * LOG10(reports_count + 1) + CASE WHEN springs.name = ? THEN %F WHEN springs.name LIKE ? THEN %F ELSE 0 END',
            self::REPORTS_WEIGHT, NameMatcher::EXACT, NameMatcher::PREFIX);
        $bindings = [$term, SearchText::likePattern($term).'%'];

        if ($area !== null) {
            [$inside, $insideBindings] = $area->sql('springs.latitude', 'springs.longitude');
            [$near, $nearBindings] = $area->expanded()->sql('springs.latitude', 'springs.longitude');
            $score .= sprintf(' + CASE WHEN %s THEN %F WHEN %s THEN %F ELSE 0 END', $inside, self::VIEW_BOOST['inside'], $near, self::VIEW_BOOST['near']);
            $bindings = [...$bindings, ...$insideBindings, ...$nearBindings];
        }

        $springs = Spring::query()->publicGeography()
            ->select(array_map(fn (string $column): string => 'springs.'.$column, self::COLUMNS))
            ->selectSub(Report::query()->visible()->selectRaw('COUNT(*)')->whereColumn('reports.spring_id', 'springs.id'), 'reports_count')
            ->whereFullText('springs.name', $against, ['mode' => 'boolean'])
            ->whereNotNull(['springs.latitude', 'springs.longitude']);

        // The name matcher needs every typed word, so words the full-text index skips must start a word here too,
        // or "st spring" would fetch sixty other springs and drop them all. These checks only see full-text matches.
        foreach (SearchText::unindexedWords($term) as $word) {
            $pattern = SearchText::likePattern($word);
            $springs->where(function (Builder $query) use ($pattern): void {
                $query->where('springs.name', 'like', $pattern.'%');

                foreach (self::WORD_SEPARATORS as $separator) {
                    $query->orWhere('springs.name', 'like', '%'.$separator.$pattern.'%');
                }
            });
        }

        return $springs
            ->orderByRaw($score.' DESC', $bindings)
            ->limit(self::CANDIDATES)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function result(Spring $spring, string $locale, string $name, ?string $context, int $reports): array
    {
        return [
            'type' => 'spring',
            'group' => 'springs',
            'id' => $spring->id,
            'name' => $name,
            'alt_name' => null,
            'context' => $context,
            'sources_count' => null,
            'sources_label' => $reports > 0
                ? trans_choice('ui.map.search.reports_count', $reports, ['count' => number_format($reports, 0, ',', ' ')], $locale)
                : null,
            'url' => $this->duoUrl->absolute(['spring' => $spring->id], $locale),
            'center' => [(float) $spring->longitude, (float) $spring->latitude],
            'bounds' => null,
            'zoom' => null,
        ];
    }

    private function typeLabel(?string $type, string $locale): string
    {
        $key = 'ui.spring.types.'.Str::snake((string) $type);

        return $type !== null && $type !== '' && trans()->has($key, $locale)
            ? __($key, [], $locale)
            : __('ui.map.search.types.spring', [], $locale);
    }

    // Settlement, region and country, without repeating a city-region such as Moscow.
    private function place(Spring $spring, string $locale): ?string
    {
        return SearchText::join(array_unique(array_filter([
            $this->placeName($spring->nearestCity, $locale),
            $this->placeName($spring->geoRegion, $locale),
            $this->placeName($spring->geoCountry, $locale),
        ])));
    }

    private function placeName(?Model $place, string $locale): ?string
    {
        return $place instanceof Country || $place instanceof Region || $place instanceof City ? SearchText::display($place->displayName($locale)) : null;
    }
}
