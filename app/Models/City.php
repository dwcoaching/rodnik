<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class City extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function springs(): HasMany
    {
        return $this->hasMany(Spring::class, 'nearest_city_id');
    }

    public function displayName(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ru' ? ($this->name_ru ?: $this->name) : $this->name;
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'geonames_id' => 'integer', 'latitude' => 'float', 'longitude' => 'float', 'population' => 'integer'];
    }
}
