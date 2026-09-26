<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\StoresGeometryFile;
use Database\Factories\TrackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Track extends Model
{
    /** @use HasFactory<TrackFactory> */
    use HasFactory;

    use Prunable;
    use StoresGeometryFile;

    public const int GUEST_RETENTION_DAYS = 30;

    protected $fillable = ['token', 'hash', 'track', 'user_id', 'name', 'summary'];

    public function geometryPath(): string
    {
        return 'uploads/'.$this->token.'.json';
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return self::query()
            ->whereNull('user_id')
            ->whereDoesntHave('maps')
            ->where('updated_at', '<=', now()->subDays(self::GUEST_RETENTION_DAYS));
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Map, $this> */
    public function maps(): HasMany
    {
        return $this->hasMany(Map::class);
    }

    /** @return array<string, mixed> */
    public function ownerData(?string $locale = null): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'name' => $this->name,
            'url' => route(localized_route_name('duo', $locale), ['t' => $this->token]),
            'created_at' => $this->created_at?->toIso8601String(),
            'distance_km' => $this->summary['distance_km'] ?? 0,
            'preview' => $this->summary['preview'] ?? [],
            'bbox' => $this->summary['bbox'] ?? null,
            'map_count' => $this->maps_count ?? 0,
            'maps' => $this->maps->map(fn (Map $map): array => ['id' => $map->id, 'title' => $map->title])->all(),
            'other_maps_count' => max(0, ($this->maps_count ?? 0) - $this->maps->count()),
            'rename_url' => route(localized_route_name('tracks.update', $locale), ['track' => $this->token]),
            'delete_url' => route(localized_route_name('tracks.destroy', $locale), ['track' => $this->token]),
            'download_url' => route(localized_route_name('tracks.download', $locale), ['track' => $this->token]),
        ];
    }

    /** @return Attribute<object, mixed> */
    protected function track(): Attribute
    {
        return $this->geometryAttribute(associative: false);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['summary' => 'array'];
    }
}
