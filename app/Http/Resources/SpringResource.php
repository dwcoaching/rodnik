<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

final class SpringResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'country' => $this->country,
            'country_ru' => $this->country_ru,
            'country_code' => $this->country_code,
            'city' => $this->city,
            'region' => $this->region,
            'city_ru' => $this->city_ru,
            'region_ru' => $this->region_ru,
            'country_id' => $this->country_id,
            'region_id' => $this->region_id,
            'nearest_city_id' => $this->nearest_city_id,
            'nearest_city_distance_km' => $this->nearest_city_distance_km,
            'name' => $this->name,
            'intermittent' => $this->intermittent,
            'drinking' => $this->drinking,
        ];
    }
}
