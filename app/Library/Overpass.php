<?php

namespace App\Library;

use App\Models\OSMTag;
use App\Models\Spring;
use App\Models\SpringRevision;
use Illuminate\Support\Facades\DB;

class Overpass
{
    static public function parse($json)
    {
        $existing = 0;
        $new = 0;

        foreach ($json->elements as $element) {
            switch ($element->type) {
                case 'node':
                    $spring = Spring::where('osm_node_id', $element->id)->first();
                    break;
                case 'way':
                    $spring = Spring::where('osm_way_id', $element->id)->first();
                    break;
            }

            $revision = new SpringRevision();

            if (! $spring) {
                $new = $new + 1;
                $spring = new Spring();
            } else {
                $existing = $existing + 1;
            }

            switch ($element->type) {
                case 'node':
                    $spring->osm_node_id = $element->id;
                    $revision->latitude = $element->lat;
                    $revision->longitude = $element->lon;
                    break;
                case 'way':
                    $spring->osm_way_id = $element->id;
                    $revision->latitude = $element->center->lat;
                    $revision->longitude = $element->center->lon;
                    break;
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

            $revision->osm_based = true;
            $revision->spring_id = $spring->id;
            $spring->load('osm_tags');

            $revision->name = $spring->parseOSMName();
            $revision->type = $spring->parseOSMType();
            $revision->seasonal = $spring->parseOSMSeasonal();
            $revision->save();

            $revision->apply();
        }

        return (object) [
            'new' => $new,
            'existing' => $existing
        ];
    }
}
