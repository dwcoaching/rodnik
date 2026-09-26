<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Gate;

final class Map extends Model
{
    /** @use HasFactory<MapFactory> */
    use HasFactory;

    protected $fillable = ['title', 'slug', 'state', 'track_id'];

    protected $attributes = [
        'version' => 1,
        'views_count' => 0,
        'is_starred' => false,
    ];

    protected $hidden = ['user_id', 'views_count', 'is_starred'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Track, $this> */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function publicData(?User $user, ?string $locale = null): array
    {
        $canUpdate = Gate::forUser($user)->allows('update', $this);
        $this->loadMissing('track:id,token,hash,name,summary');
        $track = $this->track;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'url' => route(localized_route_name('maps.show', $locale), ['map' => $this->slug]).'/',
            'login_url' => route(localized_route_name('maps.login', $locale), ['map' => $this->slug]),
            'can_update' => $canUpdate,
            ...($canUpdate ? [
                'edit_url' => route(localized_route_name('maps.edit', $locale), ['map' => $this->id]),
                'starred' => $this->is_starred,
            ] : []),
            'version' => $this->version,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'state' => $this->state,
            'track' => $track === null ? null : [
                'token' => $track->token,
                'name' => $track->name,
                'hash' => $track->hash,
                'url' => route('tracks.show', ['token' => $track->token]),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function ownerData(?User $user, ?string $locale = null): array
    {
        $data = $this->publicData($user, $locale);

        return $data['can_update'] ? [...$data,
            'created_at' => $this->created_at?->toIso8601String(),
            'track_name' => $this->track?->name,
            'preview' => $this->track?->summary['preview'] ?? [],
            'center' => $this->state['center'],
            'rename_url' => route('maps.rename', ['map' => $this->id]),
            'details_url' => route('maps.details', ['map' => $this->id]),
            'star_url' => route(localized_route_name('maps.star', $locale), ['map' => $this->id]),
            'delete_url' => route(localized_route_name('maps.destroy', $locale), ['map' => $this->id]),
        ] : $data;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => 'array',
            'version' => 'integer',
            'views_count' => 'integer',
            'is_starred' => 'boolean',
        ];
    }
}
