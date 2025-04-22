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
                   $id = intval($matches[1]);
                   // Add <desc> tag
                   $wpt->addChild('desc', self::getEnrichedDescriptionForId($id));
               }
           }
       }

       $gpxString = $gpx->asXML();
       
       return $gpxString;
    }

    static public function getEnrichedDescriptionForId(int $springId)
    {
        $spring = Spring::find($springId);

        if (! $spring) {
            return '';
        }

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

        $result = join("\n", [$osm, $reports]);

        // Escape special XML characters to ensure the result is safe for XML
        $result = htmlspecialchars($result, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        
        // Remove any null bytes or other invalid XML characters
        $result = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $result);

        return $result;
    }
}
