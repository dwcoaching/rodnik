<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Country extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function springs(): HasMany
    {
        return $this->hasMany(Spring::class);
    }

    public function displayName(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ru' ? ($this->name_ru ?: $this->name) : $this->name;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'geonames_id' => 'integer',
            'bbox_west' => 'float', 'bbox_south' => 'float', 'bbox_east' => 'float', 'bbox_north' => 'float',
        ];
    }
}
