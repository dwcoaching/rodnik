<?php

namespace App\Http\Resources;

use App\Http\Resources\ExportedReportResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ExportedSpringResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'name' => $this->name,
            'osm_node_id' => $this->osm_node_id,
            'osm_way_id' => $this->osm_way_id,
            'reports' => ExportedReportResource::collection($this->whenLoaded('reports')),
        ];
    }
}

ExportedSpringResource::withoutWrapping();
