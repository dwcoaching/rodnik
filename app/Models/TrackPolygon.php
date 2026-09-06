<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TrackPolygonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TrackPolygon extends Model
{
    /** @use HasFactory<TrackPolygonFactory> */
    use HasFactory;

    protected $fillable = [
        'hash', 'polygon', 'user_id',
        'latitude_from', 'latitude_to', 'longitude_from', 'longitude_to',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'polygon' => 'array',
            'latitude_from' => 'float',
            'latitude_to' => 'float',
            'longitude_from' => 'float',
            'longitude_to' => 'float',
        ];
    }
}
