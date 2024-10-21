<?php

namespace App\Models;

use Faker\Factory;
use App\Models\Photo;
use App\Models\OSMTag;
use App\Models\Report;
use App\Models\SpringTile;
use App\Models\SpringRevision;
use App\Library\StatisticsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Spring extends Model
{
    use HasFactory;

    public const TYPES = [
        'Spring',
        'Water well',
        'Water tap',
        'Drinking water source',
        'Fountain',
        'Water source',
        //'Not a water source',
    ];

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function springRevisions()
    {
        return $this->hasMany(SpringRevision::class);
    }

    public function osm_tags()
    {
        return $this->hasMany(OSMTag::class);
    }

    public function photos()
    {
        return $this->hasManyThrough(Photo::class, Report::class);
    }

    public function getCoordinatesAttribute()
    {
        if ($this->latitude && $this->longitude) {
            return $this->latitude . ', ' . $this->longitude;
        }

        return '';
    }

    public function parseOSMIntermittent()
    {
        $intermittentOrSeasonalTags = $this->osm_tags->filter(function($item) {
            return $item->key == 'seasonal' || $item->key == 'intermittent';
        });

        if (! $intermittentOrSeasonalTags->count()) {
            return null;
        }

        $permanentTagsCount = $intermittentOrSeasonalTags->reduce(function ($carry, $item) {
            return $carry + intval($item->value === 'no' ? 1 : 0);
        });

        if ($permanentTagsCount) {
            return 'no';
        }

        return 'yes';
    }

    public function parseOSMName()
    {
        $name = $this->osm_tags->first(function($item) {
            return $item->key == 'name';
        });

        if ($name) {
            return $name->value;
        }

        return null;
    }

    public function parseOSMType()
    {
        if (count(
            $this->osm_tags->filter(function($item) {
                return $item->key == 'natural' &&
                    ($item->value == 'spring' | $item->value == 'spring_box');
            }))) {
            return 'Spring';
        }

        if (count(
            $this->osm_tags->filter(function($item) {
                return $item->key == 'man_made' && $item->value == 'water_well';
            }))) {
            return 'Water well';
        }

        if (count(
            $this->osm_tags->filter(function($item) {
                return $item->key == 'man_made' && $item->value == 'water_tap';
            }))) {
            return 'Water tap';
        }

        if (count(
            $this->osm_tags->filter(function($item) {
                return
                    ($item->key == 'amenity' && $item->value == 'drinking_water')
                    || ($item->key == 'drinking_water' && $item->value == 'yes')
                    || ($item->key == 'man_made' && $item->value == 'drinking_fountain');
            }))) {
            return 'Drinking water source';
        }

        if (count(
            $this->osm_tags->filter(function($item) {
                return $item->key == 'amenity' && $item->value == 'fountain';
            }))) {
            return 'Fountain';
        }

        return 'Water source';
    }

    public function invalidateTiles()
    {
        SpringTile::invalidate($this->longitude, $this->latitude);
        WateredSpringTile::invalidate($this->longitude, $this->latitude);
    }

    public function updateFromOSM($key, $newValue, SpringRevision $revision)
    {
        if ($this->{'osm_' . $key} != $newValue) {
            if ($this->{$key} == $this->{'osm_' . $key}) {

                echo $newValue;
                echo "\n";
                echo $this->{'osm_' . $key};
                echo "\n";
                echo $this->{$key};
                echo "----------------\n";

                $revision->{'old_' . $key} = $this->{'osm_' . $key};
                $revision->{'new_' . $key} = $newValue;

                $this->{$key} = $newValue;
            }

            $revision->{'old_osm_' . $key} = $this->{'osm_' . $key};
            $revision->{'new_osm_' . $key} = $newValue;
            $this->{'osm_' . $key} = $newValue;
        }

        return $revision;
    }

    public function waterConfirmed()
    {
        $presenceOfGoodWaterCount = 0;
        $absenceOfGoodWaterCount = 0;

        foreach ($this->reports as $report) {
            if ($report->quality == 'good' && $report->state == 'running') {
                $presenceOfGoodWaterCount++;
            }

            if (
                    (
                        ! is_null($report->quality)
                        && $report->quality != 'good'
                    )
                    ||
                    (
                        ! is_null($report->state)
                        && $report->state != 'running'
                    )
                ) {
                $absenceOfGoodWaterCount++;
            }

            if ($presenceOfGoodWaterCount > $absenceOfGoodWaterCount) {
                return true;
            }

            return false;
        }
    }

    public function annihilate()
    {
        if (! $this->canBeAnnihilated()) {
            throw new \Exception("Spring can not be annihilated");
        }

        $latitude = $this->latitude;
        $longitude = $this->longitude;

        $this->delete();

        SpringTile::invalidate($this->longitude, $this->latitude);
        WateredSpringTile::invalidate($this->longitude, $this->latitude);
        StatisticsService::invalidateSpringsCount();
    }

    public function hide()
    {
        $this->hidden_at = now();
        $this->save();

        SpringTile::invalidate($this->longitude, $this->latitude);
        WateredSpringTile::invalidate($this->longitude, $this->latitude);
        StatisticsService::invalidateSpringsCount();
    }

    public function unhide()
    {
        $this->hidden_at = null;
        $this->save();

        SpringTile::invalidate($this->longitude, $this->latitude);
        WateredSpringTile::invalidate($this->longitude, $this->latitude);
        StatisticsService::invalidateSpringsCount();
    }

    public function canBeAnnihilated()
    {
        if ($this->reports->count() > 0) {
            return false;
        }

        if ($this->osm_node_id || $this->osm_way_id) {
            return false;
        }

        return true;
    }

    public function visible()
    {
        return ! $this->hidden_at;
    }

    //     public function apply()
    // {
    //     $this->spring->latitude = $this->latitude;
    //     $this->spring->longitude = $this->longitude;
    //     $this->spring->name = $this->name;
    //     $this->spring->type = $this->type;
    //     $this->spring->seasonal = $this->seasonal;

    //     $this->current = true;
    //     $this->save();

    //     $this->spring->save();
    //     $this->spring->invalidateTiles();
    // }


}
