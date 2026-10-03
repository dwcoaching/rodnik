<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Services\Geography\PublicGeography;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class GeographyController extends Controller
{
    private const SOURCES_PER_PAGE = 30;

    public function __construct(private readonly PublicGeography $geography) {}

    public function index(Request $request): View
    {
        abort_if($this->page($request) !== 1 || $request->integer('settlements_page', 1) !== 1, 404);
        $countries = $this->geography->countries()
            ->orderByDesc('sources_count')->orderBy('countries.name')->get();

        return view('geography.index', [
            'countries' => $countries,
            'indexable' => $countries->isNotEmpty(),
        ]);
    }

    public function country(Request $request, Country $country): View
    {
        return $this->show($request, $country, 'country');
    }

    public function region(Request $request, Region $region): View
    {
        $region->load('country');

        return $this->show($request, $region, 'region');
    }

    public function city(Request $request, City $city): View
    {
        $city->load(['country', 'region']);

        return $this->show($request, $city, 'city');
    }

    private function show(Request $request, Country|Region|City $place, string $type): View
    {
        abort_unless($place->is_active, 404);
        $page = $this->page($request);
        abort_if($place instanceof City && $request->integer('settlements_page', 1) !== 1, 404);
        $springs = $this->geography->springsFor($place)->with('geoCountry')
            ->orderBy('id')->paginate(self::SOURCES_PER_PAGE, ['*'], 'page', $page);
        abort_if($springs->isEmpty(), 404);

        $regions = $place instanceof Country
            ? $this->geography->regions($place)->orderByDesc('sources_count')->orderBy('regions.name')->get()
            : collect();
        $cities = $place instanceof City
            ? null
            : $this->geography->cities(
                $place instanceof Country ? $place : null,
                $place instanceof Region ? $place : null,
            )->orderByDesc('sources_count')->orderBy('cities.name')
                ->paginate(24, ['*'], 'settlements_page');
        $cities?->appends($page > 1 ? ['page' => $page] : []);
        abort_if($cities !== null && $request->integer('settlements_page', 1) > 1 && $cities->isEmpty(), 404);
        $springs->appends($request->integer('settlements_page') > 1 ? ['settlements_page' => $request->integer('settlements_page')] : []);

        $country = $place instanceof Country ? null : $place->country;
        $region = $place instanceof City ? $place->region : null;

        return view('geography.show', [
            'place' => $place,
            'type' => $type,
            'springs' => $springs,
            'regions' => $regions,
            'cities' => $cities,
            'country' => $country,
            'region' => $region,
            'countryHasSources' => $country instanceof Country && $country->is_active && $this->geography->springsFor($country)->exists(),
            'regionHasSources' => $region instanceof Region && $region->is_active && $this->geography->springsFor($region)->exists(),
            'indexable' => true,
        ]);
    }

    private function page(Request $request): int
    {
        foreach (['page', 'settlements_page'] as $name) {
            $value = $request->query($name, '1');
            abort_unless(is_scalar($value) && ctype_digit((string) $value), 404);
            abort_unless(filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false, 404);
        }

        return (int) $request->query('page', 1);
    }
}
