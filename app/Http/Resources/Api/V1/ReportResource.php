<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $report = $this->resource;

        if (! $report instanceof Report) {
            return [];
        }

        return [
            'id' => (int) $report->id,
            'spring_id' => (int) $report->spring_id,
            'visited_at' => $report->visited_at?->format('Y-m-d'),
            'state' => $report->state?->value,
            'quality' => $report->quality?->value,
            'access_limited' => (bool) $report->access_limited,
            'littered' => (bool) $report->littered,
            'broken' => (bool) $report->broken,
            'comment' => $report->comment,
            'author' => UserSummaryResource::make($this->whenLoaded('user')),
            'created_at' => $report->created_at?->toISOString(),
            'updated_at' => $report->updated_at?->toISOString(),
            'photos' => PhotoResource::collection($this->whenLoaded('photos')),
        ];
    }
}
