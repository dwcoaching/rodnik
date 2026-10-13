<?php

declare(strict_types=1);

namespace App\Library;

use App\Models\OSMTag;
use App\Models\Spring;
use App\Models\SpringRevision;
use App\Models\SpringTile;
use App\Models\WateredSpringTile;
use Illuminate\Support\Facades\DB;

final class Overpass
{
    /**
     * Elements looked up and stamped per round trip. A full import is ~1.4 million elements,
     * nearly all of them unchanged, so the per-element SELECT and UPDATE this replaces were
     * what kept parsing busy for hours.
     */
    public const CHUNK_SIZE = 1000;

    public static function parse($json, $batchId = null)
    {
        $stats = (object) [
            'new' => 0,
            'existing' => 0,
            'unchanged' => 0,
        ];

        foreach (array_chunk($json->elements, self::CHUNK_SIZE) as $chunk) {
            self::parseChunk($chunk, $batchId, $stats);
        }

        return $stats;
    }

    /**
     * Skip every element OSM has not touched since we last stored it, and stamp all of them as
     * seen in one statement; only new and edited elements go through the model one by one.
     */
    private static function parseChunk(array $elements, $batchId, object $stats): void
    {
        $known = self::knownSprings($elements);
        $unchangedIds = [];

        // The lookup above predates this chunk, so an element that shows up twice has to find
        // the spring its first occurrence saved rather than create it again.
        $saved = [];

        foreach ($elements as $element) {
            $spring = $saved[$element->type][$element->id] ?? null;
            $row = $known[$element->type][$element->id] ?? null;

            if (! $spring && $row && self::isUnchanged($element, $row)) {
                $stats->unchanged = $stats->unchanged + 1;
                $unchangedIds[] = $row->id;

                continue;
            }

            $spring ??= $row ? Spring::find($row->id) : null;

            $saved[$element->type][$element->id] = self::parseElement($element, $spring, $batchId, $stats);
        }

        // Written past the model on purpose: nothing about these springs changed, so neither
        // their observers nor their updated_at have anything to react to.
        if ($batchId !== null && $unchangedIds !== []) {
            DB::table('springs')
                ->whereIn('id', $unchangedIds)
                ->update(['last_seen_overpass_batch_id' => $batchId]);
        }
    }

    /**
     * What we already store for the elements of a chunk, keyed by element type and OSM id.
     *
     * @return array{node?: array<int, object>, way?: array<int, object>}
     */
    private static function knownSprings(array $elements): array
    {
        $ids = ['node' => [], 'way' => []];

        foreach ($elements as $element) {
            $ids[$element->type][] = $element->id;
        }

        $known = [];

        foreach (['node' => 'osm_node_id', 'way' => 'osm_way_id'] as $type => $column) {
            if ($ids[$type] === []) {
                continue;
            }

            $rows = Spring::query()
                ->whereIn($column, $ids[$type])
                ->toBase()
                ->get(['id', $column, 'osm_version', 'osm_latitude', 'osm_longitude']);

            foreach ($rows as $row) {
                $known[$type][(int) $row->{$column}] = $row;
            }
        }

        return $known;
    }

    /**
     * Whether an element is exactly what we stored last time.
     *
     * The version alone settles it for a node. A way's version does not move when its nodes
     * do, though, and its centre — our coordinates — is derived from those nodes, so a way also
     * has to come back at the centre we stored.
     */
    private static function isUnchanged(object $element, object $row): bool
    {
        if (! isset($element->version) || $row->osm_version === null
            || (int) $row->osm_version !== (int) $element->version) {
            return false;
        }

        if ($element->type !== 'way') {
            return true;
        }

        return isset($element->center)
            && self::sameCoordinate($row->osm_latitude, $element->center->lat)
            && self::sameCoordinate($row->osm_longitude, $element->center->lon);
    }

    /**
     * Compared at the six decimals we store, as {@see Spring::updateFromOSM()} does.
     */
    private static function sameCoordinate(mixed $stored, mixed $incoming): bool
    {
        if ($stored === null || $incoming === null) {
            return false;
        }

        return number_format((float) $stored, 6, '.', '')
            === number_format(round((float) $incoming, 6), 6, '.', '');
    }

    private static function parseElement(object $element, ?Spring $spring, $batchId, object $stats): Spring
    {
        $elementVersion = isset($element->version) ? (int) $element->version : null;

        $revision = new SpringRevision();

        if (! $spring) {
            $stats->new = $stats->new + 1;
            $spring = new Spring();
            $springExists = false;
            $oldLat = null;
            $oldLon = null;
        } else {
            $stats->existing = $stats->existing + 1;
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

        $revision = $spring->updateFromOSM('latitude', round((float) $osm_lat, 6), $revision);
        $revision = $spring->updateFromOSM('longitude', round((float) $osm_lon, 6), $revision);

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
        }

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
                && ((float) $oldLat !== (float) $spring->latitude || (float) $oldLon !== (float) $spring->longitude)) {
                SpringTile::invalidate($oldLon, $oldLat);
                WateredSpringTile::invalidate($oldLon, $oldLat);
            }
        } elseif (! $springExists) {
            $spring->invalidateTiles();
            StatisticsService::invalidateSpringsCount();
        }

        return $spring;
    }
}
