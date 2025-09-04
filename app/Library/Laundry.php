<?php

namespace App\Library;

use App\Models\OSMTag;
use App\Models\Report;
use App\Models\Spring;
use App\Library\Tagger;
use App\Models\SpringRevision;
use App\Library\StatisticsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class Laundry
{
    public function cleanup()
    {
        $query = $this->getCleaningQuery();

        $query->chunkById(100, function (Collection $springs) {
            foreach ($springs as $spring) {
                echo $spring->id . "\n";
                $spring->hide();
            }
        });
    }

    public function getCleaningQuery()
    {
        $query = Spring::whereNull('hidden_at')
            ->where(function (Builder $query) {
                $tagsArray = self::getFalsePositiveTags();

                foreach ($tagsArray as $tags) {
                    $tags = Tagger::parseTags($tags);

                    $query->orWhere(function (Builder $query) use ($tags) {
                        foreach ($tags as $tag) {
                            $query->whereHas('osm_tags', function($query) use ($tag) {
                                $query
                                    ->where('key', $tag[0])
                                    ->where('value', $tag[1]);
                            });
                        }
                    });
                }
            });

        return $query;
    }

    static public function getFalsePositiveTags()
    {
        return ['
                amenity=toilets
                drinking_water=no
            ', '
                tourism=camp_site
                drinking_water=no
            ', '
                tourism=wilderness_hut
                drinking_water=no
            ',
            '
                amenity=shelter
                drinking_water=no
            ',
            '
                tourism=alpine_hut
                drinking_water=no
            ',
            '
                highway=rest_area
                drinking_water=no
            ',
        ];
    }
}
