<?php

namespace App\Library;

use App\Models\OSMTag;
use App\Models\Spring;
use Illuminate\Support\Facades\DB;

class Overpass
{
    static public function parse($json)
    {
        $existing = 0;
        $new = 0;

        $currentIsNew = false;

        foreach ($json->elements as $element) {
            switch ($element->type) {
                case 'node':
                    $spring = Spring::where('osm_node_id', $element->id)->first();
                    break;
                case 'way':
                    $spring = Spring::where('osm_way_id', $element->id)->first();
                    break;
            }

            if (! $spring) {
                $currentIsNew = true;
                $new = $new + 1;
                $spring = new Spring();

                switch ($element->type) {
                    case 'node':
                        $spring->osm_node_id = $element->id;
                        $spring->latitude = $element->lat;
                        $spring->longitude = $element->lon;
                        break;
                    case 'way':
                        $spring->osm_way_id = $element->id;
                        $spring->latitude = $element->center->lat;
                        $spring->longitude = $element->center->lon;
                        break;
                }
            } else {
                $existing = $existing + 1;
                continue;
            }

            $spring->save();

            DB::table('osm_tags')->where('spring_id', '=', $spring->id)->delete();

            foreach ($element->tags as $key => $value) {
                $osmTag = new OSMTag();
                $osmTag->key = $key;
                $osmTag->value = $value;
                $osmTag->spring_id = $spring->id;
                $osmTag->save();
            };
        }

        return (object) [
            'new' => $new,
            'existing' => $existing
        ];
    }
}
