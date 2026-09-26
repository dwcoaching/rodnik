<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\StoresGeometryFile;
use Database\Factories\TrackPolygonFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TrackPolygon extends Model
{
    /** @use HasFactory<TrackPolygonFactory> */
    use HasFactory;

    use StoresGeometryFile;

    protected $fillable = [
        'hash', 'polygon', 'user_id',
        'latitude_from', 'latitude_to', 'longitude_from', 'longitude_to',
    ];

    public function geometryPath(): string
    {
        return 'polygons/'.$this->hash.'.json';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return Attribute<array<string, mixed>, mixed> */
    protected function polygon(): Attribute
    {
        return $this->geometryAttribute(associative: true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitude_from' => 'float',
            'latitude_to' => 'float',
            'longitude_from' => 'float',
            'longitude_to' => 'float',
        ];
    }
}
