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

        return [
            'id' => (int) $spring->id,
            'name' => $spring->name,
            'type' => $spring->type,
            'latitude' => $spring->latitude !== null ? (float) $spring->latitude : null,
            'longitude' => $spring->longitude !== null ? (float) $spring->longitude : null,
            'intermittent' => $spring->intermittent,
            'water_score' => $spring->getWaterScore(),
            'water_confirmed' => $spring->waterConfirmed(),
            'not_found' => $spring->isNotFound(),
            'reports_count' => $this->whenLoaded('visibleReports', fn (): int => $spring->visibleReports->count()),
            'reports' => ReportResource::collection($this->whenLoaded('visibleReports')),
        ];
    }
}
