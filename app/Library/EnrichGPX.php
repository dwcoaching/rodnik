<?php

namespace App\Library;

use App\Models\Spring;

class EnrichGPX
{
    static public function enrich(string $gpx)
    {
       // parse gpx
       $gpx = simplexml_load_string($gpx);

       // Register the GPX namespace
       $namespaces = $gpx->getNamespaces(true);

       // Iterate over all waypoints (wpt)
       foreach ($gpx->wpt as $wpt) {
           // Find the <link> element
           $link = $wpt->link;
           if ($link && isset($link['href'])) {
               $href = (string)$link['href'];
               // Match the id from the URL
               if (preg_match('#https://rodnik\.today/(\d+)#', $href, $matches)) {
                   $id = $matches[1];
                   // Add <desc> tag
                   $wpt->addChild('desc', self::getEnrichedDescriptionForId($id));
               }
           }
       }

       $gpxString = $gpx->asXML();
       
       return $gpxString;
    }

    static public function getEnrichedDescriptionForId($springId)
    {
        $spring = Spring::find($springId);

        $osm = '';
        $reports = '';

        if ($spring->osm_tags->count()) {
            $osm .= 'OSM tags: ' . $spring->osm_tags->map(function ($tag) {
                return $tag->key . '=' . $tag->value;
            })->join(', ') . '.';
        }

        if ($spring->reports()->visible()->count()) {
            $reports .= 'Reports: ' . $spring->reports()->visible()->get()->map(function ($report) {
                $author = $report->user ? $report->user->name : 'Unknown';
                // Map quality
                $qualityMap = [
                    'good' => '[Good Water]',
                    'bad' => '[Poor Water]',
                ];
                $quality = $qualityMap[$report->quality] ?? '';
                // Map state
                $stateMap = [
                    'running' => '[Watered]',
                    'dry' => '[Dry]',
                    'notfound' => '[Not Found]',
                ];
                $state = $stateMap[$report->state] ?? '';

                $conditions = [];
                if ($quality) {
                    $conditions[] = $quality;
                }
                if ($state) {
                    $conditions[] = $state;
                }

                $condition = '';

                if (count($conditions)) {
                    $condition .= join(' ', $conditions);
                }

                $comment = $report->comment ?? '';

                return sprintf(
                    '%s (%s) %s %s',
                    $report->visited_at->format('Y-m-d'),
                    $author,
                    $condition,
                    $comment
                );
            })->join("\n");
        }

        return join("\n", [$osm, $reports]);
    }
}
