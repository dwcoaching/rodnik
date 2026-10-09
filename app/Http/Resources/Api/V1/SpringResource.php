<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Spring;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SpringResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $spring = $this->resource;

        if (! $spring instanceof Spring) {
            return [];
        }

        $country = $spring->relationLoaded('geoCountry') ? $spring->getRelation('geoCountry') : null;
        $region = $spring->relationLoaded('geoRegion') ? $spring->getRelation('geoRegion') : null;
        $city = $spring->relationLoaded('nearestCity') ? $spring->getRelation('nearestCity') : null;

        return [
            'id' => (int) $spring->id,
            'name' => $spring->name,
            'type' => $spring->type,
            'latitude' => $spring->latitude !== null ? (float) $spring->latitude : null,
            'longitude' => $spring->longitude !== null ? (float) $spring->longitude : null,
            'country' => $country?->name,
            'country_ru' => $country?->name_ru,
            'country_code' => $country?->code,
            'city' => $city?->name,
            'region' => $region?->name,
            'city_ru' => $city?->name_ru,
            'region_ru' => $region?->name_ru,
            'country_id' => $spring->country_id,
            'region_id' => $spring->region_id,
            'nearest_city_id' => $spring->nearest_city_id,
            'nearest_city_distance_km' => $spring->nearest_city_distance_km,
            'intermittent' => $spring->intermittent,
            'water_score' => $spring->getWaterScore(),
            'not_found' => $spring->isNotFound(),
            'reports_count' => $this->whenLoaded('visibleReports', fn (): int => $spring->visibleReports->count()),
            'reports' => ReportResource::collection($this->whenLoaded('visibleReports')),
        ];
    }
}
