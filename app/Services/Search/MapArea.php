<?php

declare(strict_types=1);

namespace App\Services\Search;

use Illuminate\Database\Eloquent\Builder;

/** A box in degrees; west exceeds east across the antimeridian. */
final readonly class MapArea
{
    public function __construct(
        public float $west,
        public float $south,
        public float $east,
        public float $north,
    ) {}

    /** @param  array{west: float, south: float, east: float, north: float}  $view */
    public static function fromView(array $view): self
    {
        return new self($view['west'], $view['south'], $view['east'], $view['north']);
    }

    public function contains(float $latitude, float $longitude): bool
    {
        if ($latitude < $this->south || $latitude > $this->north) {
            return false;
        }

        return $this->west <= $this->east
            ? $longitude >= $this->west && $longitude <= $this->east
            : $longitude >= $this->west || $longitude <= $this->east;
    }

    /** The area grown by its own size on every side. */
    public function expanded(): self
    {
        $width = $this->east >= $this->west ? $this->east - $this->west : $this->east - $this->west + 360;
        $height = $this->north - $this->south;
        $south = max(-90.0, $this->south - $height);
        $north = min(90.0, $this->north + $height);

        if ($width * 3 >= 360) {
            return new self(-180.0, $south, 180.0, $north);
        }

        $wrap = fn (float $longitude): float => fmod($longitude + 540.0, 360.0) - 180.0;

        return new self($wrap($this->west - $width), $south, $wrap($this->east + $width), $north);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function constrain(Builder $query, string $latitude = 'latitude', string $longitude = 'longitude'): Builder
    {
        return $query->whereRaw(...$this->sql($latitude, $longitude));
    }

    /**
     * A condition that holds for coordinates in the area, and its bindings.
     *
     * @return array{string, list<float>}
     */
    public function sql(string $latitude = 'latitude', string $longitude = 'longitude'): array
    {
        $longitudes = $this->west <= $this->east ? "{$longitude} BETWEEN ? AND ?" : "({$longitude} >= ? OR {$longitude} <= ?)";

        return ["({$latitude} BETWEEN ? AND ? AND {$longitudes})", [$this->south, $this->north, $this->west, $this->east]];
    }
}
