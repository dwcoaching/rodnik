<?php

namespace App\Models;

use Faker\Factory;
use App\Models\OSMTag;
use App\Models\Report;
use App\Models\SpringTile;
use App\Models\SpringRevision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Spring extends Model
{
    use HasFactory;

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

    public function updateFromOSM($key, $newValue, Report $report)
    {
        if ($this->{'osm_' . $key} != $newValue) {
            if ($this->{$key} == $this->{'osm_' . $key}) {

                echo $newValue;
        echo "\n";
        echo $this->{'osm_' . $key};
        echo "\n";
        echo $this->{$key};
        echo "----------------\n";


                $report->{'old_' . $key} = $this->{'osm_' . $key};
                $report->{'new_' . $key} = $newValue;

                $this->{$key} = $newValue;
            }

            $this->{'osm_' . $key} = $newValue;
        }

        return $report;
    }

    public function waterConfirmed()
    {
        $presenceOfGoodWaterCount = 0;
        $absenceOfGoodWaterCount = 0;

        foreach ($this->reports()->visible()->get() as $report) {
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
