<?php

namespace App\Library;

use App\Models\OSMTag;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringRevision;
use App\Models\SpringTile;
use App\Models\WateredSpringTile;
use App\Library\StatisticsService;
use Illuminate\Support\Facades\DB;

class Overpass
{
    static public function parse($json, $batchId = null)
    {
        $existing = 0;
        $new = 0;
        $unchanged = 0;

        foreach ($json->elements as $element) {
            switch ($element->type) {
                case 'node':
                    $spring = Spring::where('osm_node_id', $element->id)->first();
                    break;
                case 'way':
                    $spring = Spring::where('osm_way_id', $element->id)->first();
                    break;
            }

            $elementVersion = isset($element->version) ? (int) $element->version : null;

            if ($spring && $elementVersion !== null && $spring->osm_version !== null
                && (int) $spring->osm_version === $elementVersion) {
                $unchanged = $unchanged + 1;
                if ($batchId !== null) {
                    $spring->last_seen_overpass_batch_id = $batchId;
                    $spring->save();
                }
                continue;
            }

            $revision = new SpringRevision();

            if (! $spring) {
                $new = $new + 1;
                $spring = new Spring();
                $springExists = false;
                $oldLat = null;
                $oldLon = null;
            } else {
                $existing = $existing + 1;
                $springExists = true;
                $oldLat = $spring->latitude;
                $oldLon = $spring->longitude;
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

            $revision = $spring->updateFromOSM('latitude', round(floatval($osm_lat), 6), $revision);
            $revision = $spring->updateFromOSM('longitude', round(floatval($osm_lon), 6), $revision);

            if ($elementVersion !== null) {
                $spring->osm_version = $elementVersion;
            }

            if ($batchId !== null) {
                $spring->last_seen_overpass_batch_id = $batchId;
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

            $spring->load('osm_tags');

            $osm_name = $spring->parseOSMName();
            $osm_type = $spring->parseOSMType();
            $osm_intermittent = $spring->parseOSMIntermittent();

            $revision = $spring->updateFromOSM('name', $osm_name, $revision);
            $revision = $spring->updateFromOSM('type', $osm_type, $revision);
            $revision = $spring->updateFromOSM('intermittent', $osm_intermittent, $revision);

            $spring->save();

            if ($springExists && $revision->isDirty()) {
                $revision->revision_type = 'from_osm';
                $revision->spring_id = $spring->id;
                $revision->save();
                $spring->invalidateTiles();

                if ($oldLat !== null && $oldLon !== null
                    && ($oldLat != $spring->latitude || $oldLon != $spring->longitude)) {
                    SpringTile::invalidate($oldLon, $oldLat);
                    WateredSpringTile::invalidate($oldLon, $oldLat);
                }
            } elseif (! $springExists) {
                $spring->invalidateTiles();
                StatisticsService::invalidateSpringsCount();
            }
        }

        return (object) [
            'new' => $new,
            'existing' => $existing,
            'unchanged' => $unchanged,
        ];
    }
}
