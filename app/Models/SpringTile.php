<?php

namespace App\Models;

use App\Library\Tile;
use App\Models\Spring;
use App\Library\SpringsGeoJSON;
use Illuminate\Support\Facades\DB;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SpringTile extends Model
{
    use HasFactory;

    const DISK = 'tiles';

    const LIMITS = [
        '0' => 1000,
        '5' => 1000,
        '8' => 0,
    ];

    protected $fillable = [
        'z', 'x', 'y'
    ];

    protected $geoJSONString = null;

    static public function fromXYZ($x, $y, $z)
    {
        return static::firstOrCreate([
            'x' => $x,
            'y' => $y,
            'z' => $z
        ]);
    }

    static public function fromCoordinates($longitude, $latitude)
    {
        $zoom = collect([0, 5, 8]);

        $springTiles = $zoom->map(function ($z) use ($longitude, $latitude) {
            $x = floor((($longitude + 180) / 360) * pow(2, $z));
            $y = floor((1 - log(tan(deg2rad($latitude)) + 1 / cos(deg2rad($latitude))) / pi()) /2 * pow(2, $z));

            return static::fromXYZ($x, $y, $z);
        });

        return $springTiles;
    }

    static public function invalidate($longitude, $latitude)
    {
        $springTiles = static::fromCoordinates($longitude, $latitude);

        $springTiles->each(function($item) {
            $item->deleteFile();
        });

        return $springTiles;
    }

    public function geoJSON()
    {
        if ($this->geoJSONString === null) {
            $this->geoJSONString = $this->generateGeoJSONString();
        }

        return $this->geoJSONString;
    }

    public function getLimit()
    {
        return static::LIMITS[$this->z];
    }

    public function generateGeoJSONString()
    {
        $springsQuery = Spring::query();

        if ($this->getLimit()) {
            $randomQuery = $this->getRandomQuery();

            $springsQuery->joinSub($randomQuery, 'randomSprings', function($join) {
                $join->on('springs.id', '=', 'randomSprings.id');
            });
        } else {
            $springsQuery
                ->where($this->getCoordinatesFunction())
                ->withCount(
                    [
                        'reports' => function(Builder $query) {
                            $query
                                ->whereNull('hidden_at')
                                ->whereNull('from_osm');
                        }
                    ]
                );
        }

        Debugbar::startMeasure('sql',);
        $springs = $springsQuery->get();
        Debugbar::stopMeasure('sql');

        return SpringsGeoJSON::convert($springs);
    }

    public function path()
    {
        return '/' . $this->z . '/' . $this->x . '/' . $this->y . '.json';
    }

    public function saveFile()
    {
        Storage::disk(static::DISK)->put($this->path(), $this->geoJSON());

        $this->generated_at = now();
        $this->save();
    }

    public function deleteFile()
    {
        if (Storage::disk(static::DISK)->exists($this->path())) {
            Storage::disk(static::DISK)->delete($this->path());

            $this->generated_at = null;
            $this->save();
        }
    }

    public function getRandomQuery()
    {
        return DB::table('springs')
            ->select('springs.id')
            ->where($this->getCoordinatesFunction())
            ->inRandomOrder()
            ->limit($this->getLimit());
    }

    public function getCoordinatesFunction() {
        $tileCount = pow(2, $this->z);
        $longitude_from = $this->x / $tileCount * 360 - 180;
        $longitude_to = ($this->x + 1) / $tileCount * 360 - 180;

        $latitude_from = rad2deg(atan(sinh(pi() * (1 - 2 * ($this->y + 1) / $tileCount))));
        $latitude_to = rad2deg(atan(sinh(pi() * (1 - 2 * $this->y / $tileCount))));

        $coordinatesFunction = function($query) use ($latitude_from, $latitude_to, $longitude_from, $longitude_to) {
            $query->where('latitude', '>', $latitude_from)
                ->where('latitude', '<', $latitude_to)
                ->where('longitude', '>', $longitude_from)
                ->where('longitude', '<', $longitude_to);
        };

        return $coordinatesFunction;
    }
}
