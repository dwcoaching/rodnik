<?php

declare(strict_types=1);

namespace App\Support\Docs;

use App\Models\Map;
use App\Support\PageNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class PublicMaps
{
    /**
     * Below this many maps the public list is a single page. Map rows carry
     * route previews, so this is lower than the 5,000 used for place lists.
     */
    public const int SINGLE_PAGE_LIMIT = 1000;

    public const int PER_PAGE = 1000;

    public function __construct(
        private readonly int $singlePageLimit = self::SINGLE_PAGE_LIMIT,
        private readonly int $perPage = self::PER_PAGE,
    ) {}

    /**
     * Anyone with a track's link can attach it to their own map, so public lists only name and draw tracks
     * that the map's author uploaded, or that a guest did.
     */
    public static function listsTrack(Map $map): bool
    {
        return $map->track !== null && ($map->track->user_id === null || (int) $map->track->user_id === (int) $map->user_id);
    }

    public function count(): int
    {
        return Map::query()->count();
    }

    /** @return Collection<int, Map> */
    public function top(int $limit): Collection
    {
        return $this->query()->limit($limit)->get();
    }

    /** @return LengthAwarePaginator<int, Map> */
    public function paginate(): LengthAwarePaginator
    {
        $maps = $this->query()->paginate(fn (int $total): int => $this->perPage($total), page: PageNumber::from(request()));
        abort_if($maps->currentPage() > $maps->lastPage(), 404);

        return $maps;
    }

    public function perPage(int $total): int
    {
        return $total < $this->singlePageLimit ? max($total, 1) : $this->perPage;
    }

    /** @return Builder<Map> */
    private function query(): Builder
    {
        return Map::query()
            ->select(['id', 'slug', 'title', 'state', 'user_id', 'track_id', 'version', 'updated_at'])
            ->with(['user:id,name', 'track:id,user_id,token,hash,name,summary'])
            ->orderByDesc('views_count')
            ->orderByDesc('updated_at')
            ->orderBy('id');
    }
}
