<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps a model's GeoJSON geometry in a file on the tracks disk instead of a database column.
 *
 * Assigning the geometry attribute buffers it until the model is saved; deleting the model
 * removes the file once the surrounding transaction commits.
 */
trait StoresGeometryFile
{
    public const string GEOMETRY_DISK = 'tracks';

    private ?string $pendingGeometry = null;

    abstract public function geometryPath(): string;

    public static function bootStoresGeometryFile(): void
    {
        static::saved(function (self $model): void {
            if ($model->pendingGeometry !== null) {
                Storage::disk(self::GEOMETRY_DISK)->put($model->geometryPath(), $model->pendingGeometry);
                $model->pendingGeometry = null;
            }
        });

        static::deleted(function (self $model): void {
            $path = $model->geometryPath();
            $model->getConnection()->afterCommit(fn (): bool => Storage::disk(self::GEOMETRY_DISK)->delete($path));
        });
    }

    /** @return Attribute<mixed, mixed> */
    protected function geometryAttribute(bool $associative): Attribute
    {
        return Attribute::make(
            get: fn (): mixed => json_decode(
                $this->pendingGeometry ?? Storage::disk(self::GEOMETRY_DISK)->get($this->geometryPath()),
                $associative, 32, JSON_THROW_ON_ERROR,
            ),
            set: function (mixed $value): array {
                $this->pendingGeometry = is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR);

                return [];
            },
        )->withoutObjectCaching();
    }
}
