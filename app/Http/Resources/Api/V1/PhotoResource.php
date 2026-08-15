<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PhotoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $photo = $this->resource;

        if (! $photo instanceof Photo) {
            return [];
        }

        return [
            'id' => (int) $photo->id,
            'url' => $photo->url,
            'width' => $photo->width !== null ? (int) $photo->width : null,
            'height' => $photo->height !== null ? (int) $photo->height : null,
        ];
    }
}
