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

    public function osm_tags()
    {
        return $this->hasMany(OSMTag::class);
    }

    public function getIntermittentAttribute()
    {
        // $faker = Factory::create();

        // return $faker->randomElement(['yes', 'no', 'unknown']);

        // if there is a tag intermittent=no or seasonal=no

        $intermittentOrSeasonalTags = $this->osm_tags->filter(function($item) {
            return $item->key == 'seasonal' || $item->key == 'intermittent';
        });

        if (! $intermittentOrSeasonalTags->count()) {
            return 'unknown';
        }

        $permanentTagsCount = $intermittentOrSeasonalTags->reduce(function ($carry, $item) {
            return $carry + intval($item->value === 'no' ? 1 : 0);
        });

        if ($permanentTagsCount) {
            return 'no';
        }

        return 'yes';
    }

    public function getDrinkingAttribute()
    {
        // $faker = Factory::create();

        // return $faker->randomElement(['yes', 'no', 'conditional', 'unknown']);



        // amenity=drinking_water
        // drinking_water=yes
        // и natural=spring без drinking_water=no — синие, остальные — серые
        // foreach ($this->osm_tags as $tag) {
        //     if ($tag->key == 'amenity') {
        //         if ($tag->value == 'drinking_water') {
        //             return 'yes';
        //         }
        //     }

        //     if ($tag->key == 'drinking_water') {
        //         if ($tag->value == 'yes') {
        //             return 'yes';
        //         }
        //     }

        //     if ($tag->key == 'natural') {
        //         if ($tag->value == 'spring') {
        //             if ($this->osm_tags->doesntContain(function($item) {
        //                 return $item->key == 'drinking_water' && $item->value == 'no';
        //             })) {
        //                 return 'yes';
        //             }
        //         }
        //     }
        // }

        foreach ($this->osm_tags as $tag) {
            if ($tag->key == 'drinking_water') {
                if ($tag->value == 'no') {
                    return 'no';
                }
            }

            if ($tag->key == 'amenity') {
                if ($tag->value == 'fountain') {
                    if ($this->osm_tags->doesntContain(function($item) {
                        return $item->key == 'drinking_water' && $item->value == 'yes';
                    })) {
                        return 'no';
                    }
                }
            }
        }

        return 'unknown';
    }

    public function name(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                return $value ? $value : $this->type();
            }
        );
    }

    public function getCoordinatesAttribute()
    {
        if ($this->latitude && $this->longitude) {
            return $this->latitude . ', ' . $this->longitude;
        }

        return '';
    }

    public function type()
    {
        if ($this->type) {
            return $this->type;
        }

        if (count(
            $this->osm_tags->filter(function($item) {
                return $item->key == 'natural' &&
                    ($item->value == 'spring' | $item->value == 'spring_box');
            }))) {
            return 'Родник';
        }

        if (count(
            $this->osm_tags->filter(function($item) {
                return $item->key == 'man_made' && $item->value == 'water_well';
            }))) {
            return 'Колодец';
        }

        if (count(
            $this->osm_tags->filter(function($item) {
                return $item->key == 'man_made' && $item->value == 'water_tap';
            }))) {
            return 'Кран';
        }

        if (count(
            $this->osm_tags->filter(function($item) {
                return
                    ($item->key == 'amenity' && $item->value == 'drinking_water')
                    || ($item->key == 'drinking_water' && $item->value == 'yes')
                    || ($item->key == 'man_made' && $item->value == 'drinking_fountain');
            }))) {
            return 'Источник питьевой воды';
        }

        if (count(
            $this->osm_tags->filter(function($item) {
                return $item->key == 'amenity' && $item->value == 'fountain';
            }))) {
            return 'Фонтан';
        }

        return 'Источник воды';
    }

    public function invalidateTiles()
    {
        return SpringTile::invalidate($this->longitude, $this->latitude);
    }

    public function applyRevision(SpringRevision $revision)
    {
        $this->latitude = $revision->latitude;
        $this->longitude = $revision->longitude;
        $this->name = $revision->name;
        $this->type = $revision->type;
        $this->seasonal = $revision->seasonal;

        $revision->current = true;
        $revision->save();

        $this->save();
        $this->invalidateTiles();
    }
}
