<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

final class ExportedReportResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'visited_at' => $this->visited_at,
            'quality' => $this->quality?->value,
            'state' => $this->state?->value,
            'access' => $this->access?->value,
            'littered' => $this->littered,
            'ruined' => $this->ruined,
            'comment' => $this->comment,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'hidden_at' => $this->hidden_at,
            'photos' => ExportedPhotoResource::collection($this->whenLoaded('photos')),
        ];
    }
}

ExportedReportResource::withoutWrapping();
