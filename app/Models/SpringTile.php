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

    protected $fillable = [
        'z', 'x', 'y'
    ];

    protected $geoJSONString = null;

    static public function fromXYZ($x, $y, $z)
    {
        return self::firstOrCreate([
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

            return self::fromXYZ($x, $y, $z);
        });

        return $springTiles;
    }

    static public function invalidate($longitude, $latitude)
    {
        $springTiles = self::fromCoordinates($longitude, $latitude);

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

    public function generateGeoJSONString()
    {
        $tileCount = pow(2, $this->z);
        $longitude_from = $this->x / $tileCount * 360 - 180;
        $longitude_to = ($this->x + 1) / $tileCount * 360 - 180;

        $latitude_from = rad2deg(atan(sinh(pi() * (1 - 2 * ($this->y + 1) / $tileCount))));
        $latitude_to = rad2deg(atan(sinh(pi() * (1 - 2 * $this->y / $tileCount))));

        switch ($this->z) {
            case '0':
            case '5':
                $limit = 1000;
                break;
            default:
                $limit = 0;
                break;
        }

        $springsQuery = Spring::query();

        $coordinatesFunction = function($query) use ($latitude_from, $latitude_to, $longitude_from, $longitude_to) {
            $query->where('latitude', '>', $latitude_from)
                ->where('latitude', '<', $latitude_to)
                ->where('longitude', '>', $longitude_from)
                ->where('longitude', '<', $longitude_to);
        };

        $randomQuery = DB::table('springs')
            ->select('id')
            ->where($coordinatesFunction)
            ->inRandomOrder()
            ->limit($limit);

        if ($limit) {
            $springsQuery->joinSub($randomQuery, 'randomSprings', function($join) {
                $join->on('springs.id', '=', 'randomSprings.id');
            });
        } else {
            $springsQuery
                ->where($coordinatesFunction)
                ->withCount(
                    [
                        'reports' => function(Builder $query) {
                            $query->whereNull('hidden_at');
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
        Storage::disk('tiles')->put($this->path(), $this->geoJSON());

        $this->generated_at = now();
        $this->save();
    }

    public function deleteFile()
    {
        if (Storage::disk('tiles')->exists($this->path())) {
            Storage::disk('tiles')->delete($this->path());

            $this->generated_at = null;
            $this->save();
        }
    }
}
