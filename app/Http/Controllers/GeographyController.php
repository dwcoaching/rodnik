<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Services\Geography\PublicGeography;
use App\Support\PageNumber;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class GeographyController extends Controller
{
    private const TOP_SETTLEMENTS = 24;

    private const MOST_REPORTED = 10;

    public function __construct(private readonly PublicGeography $geography) {}

    public function index(Request $request): View
    {
        $this->dropStalePages($request, false);
        $countries = $this->geography->alphabetical($this->geography->countryDirectory());

        return view('geography.index', [
            'countries' => $countries,
            'indexable' => $countries->isNotEmpty(),
        ]);
    }

    public function country(Request $request, Country $country): View
    {
        $total = $this->sourcesCount($country);
        $outside = $this->geography->alphabetical($this->geography->settlementDirectory($country));
        $paginated = $outside->count() >= $this->listLimit();
        $this->dropStalePages($request, $paginated);

        return view('geography.show', [
            ...$this->pageData($country, 'country', $total),
            'regions' => $this->geography->alphabetical($this->geography->regionDirectory($country)),
            'settlements' => $this->listing($request, $outside, $paginated),
            'topSettlements' => $this->geography->topSettlements($country, self::TOP_SETTLEMENTS),
            'mostReported' => $this->geography->mostReported($country, self::MOST_REPORTED),
        ]);
    }

    public function region(Request $request, Region $region): View
    {
        $region->load('country');
        $total = $this->sourcesCount($region);
        $settlements = $this->geography->alphabetical($this->geography->settlementDirectory($region));
        $paginated = $settlements->count() >= $this->listLimit();
        $this->dropStalePages($request, $paginated);

        return view('geography.show', [
            ...$this->pageData($region, 'region', $total),
            'settlements' => $this->listing($request, $settlements, $paginated),
            'mostReported' => $this->geography->mostReported($region, self::MOST_REPORTED),
        ]);
    }

    public function city(Request $request, City $city): View
    {
        $city->load(['country', 'region']);
        $total = $this->sourcesCount($city);
        $paginated = $total >= $this->listLimit();
        $this->dropStalePages($request, $paginated);
        $page = $paginated ? $this->currentPage($request) : 1;
        $springs = $this->geography->springsFor($city)
            ->select(['id', 'name', 'type', 'latitude', 'longitude', 'country_id', 'nearest_city_distance_km'])
            ->with('geoCountry:id,name,name_ru')
            ->orderBy('nearest_city_distance_km')->orderBy('id')
            ->paginate($paginated ? $this->pageSize() : $total, ['*'], 'page', $page, $total);
        abort_if($springs->isEmpty(), 404);

        return view('geography.show', [...$this->pageData($city, 'city', $total), 'springs' => $springs]);
    }

    public function legacy(Request $request): RedirectResponse
    {
        $type = (string) $request->route('type');
        $slug = $request->route('slug');
        $url = is_string($slug)
            ? localized_route('geography.'.$type.'.show', [Str::singular($type) => $slug])
            : localized_route('geography.countries.index');
        // Old pages held 30 sources or 24 settlements; their numbers mean nothing on the longer pages now.
        $query = Arr::except($request->query(), ['page', 'settlements_page']);

        return redirect()->to($query === [] ? $url : $url.'?'.Arr::query($query), 301);
    }

    private function sourcesCount(Country|Region|City $place): int
    {
        abort_unless($place->is_active, 404);
        $total = $this->geography->springsFor($place)->count();
        abort_if($total === 0, 404);

        return $total;
    }

    /** @return array<string, mixed> */
    private function pageData(Country|Region|City $place, string $type, int $total): array
    {
        $country = $place instanceof Country ? null : $place->country;
        $region = $place instanceof City ? $place->region : null;

        return [
            'place' => $place,
            'type' => $type,
            'total' => $total,
            'country' => $country,
            'region' => $region,
            'countryHasSources' => $country instanceof Country && $country->is_active && $this->geography->springsFor($country)->exists(),
            'regionHasSources' => $region instanceof Region && $region->is_active && $this->geography->springsFor($region)->exists(),
            'indexable' => true,
        ];
    }

    /**
     * Unpaginated pages redirect away any page parameter. Settlement lists
     * no longer have a page parameter of their own.
     */
    private function dropStalePages(Request $request, bool $paginated): void
    {
        $stale = $paginated ? ['settlements_page'] : ['page', 'settlements_page'];
        if (Arr::only($request->query(), $stale) === []) {
            return;
        }

        $query = Arr::except($request->query(), $stale);

        throw new HttpResponseException(redirect()->to($query === [] ? $request->url() : $request->url().'?'.Arr::query($query), 301));
    }

    /**
     * @template T
     *
     * @param  Collection<int, T>  $items
     * @return LengthAwarePaginator<int, T>
     */
    private function listing(Request $request, Collection $items, bool $paginated): LengthAwarePaginator
    {
        $page = $paginated ? $this->currentPage($request) : 1;
        $perPage = $paginated ? $this->pageSize() : max(1, $items->count());
        $listing = new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, [
            'path' => $request->url(),
            'pageName' => 'page',
        ]);
        abort_if($page > $listing->lastPage(), 404);

        return $listing;
    }

    private function currentPage(Request $request): int
    {
        return PageNumber::from($request);
    }

    private function listLimit(): int
    {
        return max(1, (int) config('geography.list_limit'));
    }

    private function pageSize(): int
    {
        return max(1, (int) config('geography.page_size'));
    }
}
