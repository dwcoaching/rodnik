<?php

namespace App\Library;

use App\Models\OSMTag;
use App\Models\Report;
use App\Models\Spring;
use App\Library\StatisticsService;
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

            $report = new Report();

            if (! $spring) {
                $new = $new + 1;
                $spring = new Spring();
                $springExists = false;
            } else {
                $existing = $existing + 1;
                $springExists = true;
            }

            switch ($element->type) {
                case 'node':
                    $spring->osm_node_id = $element->id;
                    $osm_lat = $element->lat;
                    $osm_lon = $element->lon;
                    break;
                case 'way':
                    $spring->osm_way_id = $element->id;
                    $osm_lat = $element->center->lat;
                    $osm_lon = $element->center->lon;
                    break;
            }

            $report = $spring->updateFromOSM('latitude', round(floatval($osm_lat), 6), $report);
            $report = $spring->updateFromOSM('longitude', round(floatval($osm_lon), 6), $report);

            $spring->save();

            DB::table('osm_tags')->where('spring_id', '=', $spring->id)->delete();

            foreach ($element->tags as $key => $value) {
                $osmTag = new OSMTag();
                $osmTag->key = $key;
                $osmTag->value = $value;
                $osmTag->spring_id = $spring->id;
                $osmTag->save();
            };

            $spring->load('osm_tags');

            $osm_name = $spring->parseOSMName();
            $osm_type = $spring->parseOSMType();
            $osm_intermittent = $spring->parseOSMIntermittent();

            $report = $spring->updateFromOSM('name', $osm_name, $report);
            $report = $spring->updateFromOSM('type', $osm_type, $report);
            $report = $spring->updateFromOSM('intermittent', $osm_intermittent, $report);

            $spring->save();

            if ($springExists && $report->isDirty()) {
                $report->from_osm = true;
                $report->spring_id = $spring->id;
                $report->save();
                $spring->invalidateTiles();
            } elseif (! $springExists) {
                $spring->invalidateTiles();
                StatisticsService::invalidateSpringsCount();
            }
        }

        return (object) [
            'new' => $new,
            'existing' => $existing
        ];
    }
}
