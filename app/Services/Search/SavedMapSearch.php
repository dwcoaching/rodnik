<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Map;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use IntlDateFormatter;

final class SavedMapSearch
{
    public const LIMIT = 4;

    private const MIN_LENGTH = 3;

    private const CANDIDATES = 100;

    private const WORDS = 4;

    private const VIEWS_WEIGHT = 0.5;

    private const OWN_BOOST = 1.0;

    private const STARRED_BOOST = 0.3;

    private const SLUG = '[a-zA-Z0-9-]{3,80}';

    /**
     * Saved maps of every user titled like the term, best first.
     *
     * @return list<array{bonus: float, item: array<string, mixed>}>
     */
    public function titled(string $term, NameMatcher $matcher, string $locale, ?User $user): array
    {
        if (SearchText::baseLength($term) < self::MIN_LENGTH) {
            return [];
        }

        $words = array_slice(array_unique(preg_split('/[^\p{L}\p{M}\p{N}]+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: []), 0, self::WORDS);
        $pattern = SearchText::likePattern($term);
        // Titles containing the text anywhere: the name matcher then keeps word starts after any punctuation,
        // such as «Крым» весной, "Поход (Крым)" or "Алтай/Крым".
        $maps = Map::query()
            ->select(['id', 'user_id', 'title', 'slug', 'state', 'views_count', 'is_starred', 'updated_at'])
            ->where(function (Builder $query) use ($pattern, $words): void {
                $query->where('title', 'like', '%'.$pattern.'%');

                if (count($words) > 1) {
                    // Words in any order, as the name matcher takes them: "spring crimea" finds "Crimea in spring".
                    $query->orWhere(function (Builder $query) use ($words): void {
                        foreach ($words as $word) {
                            $query->where('title', 'like', '%'.SearchText::likePattern($word).'%');
                        }
                    });
                }
            })
            // The limit applies before ranking, so the likeliest maps come first: many maps share default titles.
            ->orderByRaw('title = ? DESC', [$term])
            ->orderByRaw('title LIKE ? DESC', [$pattern.'%'])
            ->when($user !== null, fn (Builder $query): Builder => $query->orderByRaw('user_id = ? DESC', [$user?->getKey()]))
            ->orderByDesc('views_count')->orderByDesc('id')
            ->limit(self::CANDIDATES)
            ->get();
        $hits = [];

        foreach ($maps as $map) {
            $bonus = $matcher->bonus($map->title);

            if ($bonus !== null) {
                $own = $user !== null && (int) $map->user_id === (int) $user->getKey();
                // Stars are private to their owner, so they only help the owner's own search.
                $hits[] = ['map' => $map, 'bonus' => $bonus, 'score' => $bonus + self::VIEWS_WEIGHT * log10($map->views_count + 1)
                    + ($own ? self::OWN_BOOST : 0.0) + ($own && $map->is_starred ? self::STARRED_BOOST : 0.0)];
            }
        }

        // Ties go by title, then the newest map first.
        usort($hits, fn (array $a, array $b): int => [$b['score'], $a['map']->title, $b['map']->id] <=> [$a['score'], $b['map']->title, $a['map']->id]);
        $hits = array_slice($hits, 0, self::LIMIT);
        (new Collection(array_column($hits, 'map')))->load('user:id,name');

        return array_map(fn (array $hit): array => ['bonus' => $hit['bonus'], 'item' => $this->item($hit['map'], $locale)], $hits);
    }

    public function find(string $slug): ?Map
    {
        // Slugs are case-sensitive, and other characters cannot be compared with the ASCII column.
        return preg_match('/^'.self::SLUG.'$/', $slug) === 1 ? Map::query()->where('slug', $slug)->first() : null;
    }

    /** @return array<string, mixed> */
    public function item(Map $map, string $locale): array
    {
        $state = is_array($map->state) ? $map->state : [];
        $center = $state['center'] ?? null;
        $center = is_array($center) && array_is_list($center) && count($center) === 2
            && $this->finite($center[0], 180) && $this->finite($center[1], 90) ? [(float) $center[0], (float) $center[1]] : null;
        $zoom = $state['zoom'] ?? null;

        return [
            'type' => 'map',
            'group' => 'maps',
            'id' => $map->id,
            'name' => $map->title,
            'alt_name' => null,
            'context' => SearchText::join([$map->user?->name, $this->date($map->updated_at, $locale)], ' · '),
            'sources_count' => null,
            'sources_label' => null,
            'url' => route(localized_route_name('maps.show', $locale), ['map' => $map->slug]).'/',
            'center' => $center,
            'bounds' => null,
            'zoom' => $center !== null && $this->finite($zoom, 28) && $zoom >= 0 ? (float) $zoom : null,
        ];
    }

    private function finite(mixed $value, float $limit): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value) && abs($value) <= $limit;
    }

    private function date(?DateTimeInterface $date, string $locale): ?string
    {
        if ($date === null) {
            return null;
        }

        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $date->getTimezone(), IntlDateFormatter::GREGORIAN, 'd MMM y');

        return $formatter->format($date) ?: null;
    }
}
