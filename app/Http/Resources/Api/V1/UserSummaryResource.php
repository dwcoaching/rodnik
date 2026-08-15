<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        if (! $user instanceof User) {
            return [];
        }

        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'profile_photo_url' => $user->profile_photo_url,
        ];
    }
}
