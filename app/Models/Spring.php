<?php

namespace App\Models;

use Faker\Factory;
use App\Models\OSMTag;
use App\Models\Review;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Spring extends Model
{
    use HasFactory;

    public function reviews()
    {
        return $this->hasMany(Review::class);
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
}
