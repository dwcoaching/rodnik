<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExportedReportResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'visited_at' => $this->visited_at,
            'quality' => $this->quality,
            'state' => $this->state,
            'comment' => $this->comment,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'hidden_at' => $this->hidden_at,
            'photos' => ExportedPhotoResource::collection($this->whenLoaded('photos')),
        ];
    }
}

ExportedReportResource::withoutWrapping();
