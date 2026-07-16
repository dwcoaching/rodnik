<?php

declare(strict_types=1);

namespace App\Library;

use App\Models\Spring;
use Illuminate\Support\Str;

final class EnrichGPX
{
    public static function enrich(string $gpx)
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
                $href = (string) $link['href'];
                // Match the id from the URL
                if (preg_match('#https://rodnik\.today/(\d+)#', $href, $matches)) {
                    $id = (int) ($matches[1]);
                    // Add <desc> tag
                    $wpt->addChild('desc', self::getEnrichedDescriptionForId($id));
                }
            }
        }

        $gpxString = $gpx->asXML();

        return $gpxString;
    }

    public static function getEnrichedDescriptionForId(int $springId)
    {
        $spring = Spring::find($springId);

        if (! $spring) {
            return '';
        }

        $osm = '';
        $reports = '';

        if ($spring->osm_tags->count()) {
            $osm .= 'OSM tags: '.$spring->osm_tags->map(function ($tag) {
                return $tag->key.'='.$tag->value;
            })->join(', ').'.';
        }

        if ($spring->reports()->visible()->count()) {
            // Sort reports by visited_at in descending order
            // Reports with null visited_at are sorted by created_at and placed at the end
            // The sorting uses an array of criteria:
            // 1. Reports with null visited_at get priority 1, others get 0 (to push nulls to the end)
            // 2. For date comparison, use visited_at if available, otherwise fall back to created_at
            // 3. Use negative ID as final tiebreaker to ensure consistent ordering
            $reports .= 'Reports: '.$spring->reports()->visible()->get()->sortByDesc(function ($report) {
                return [$report->visited_at === null ? 0 : 1, $report->visited_at ?: $report->created_at, -$report->id];
            })->map(function ($report) {
                $author = $report->user ? $report->user->name : 'Unknown';
                $conditions = [];
                if ($report->quality !== null) {
                    $conditions[] = '['.Str::title($report->quality->getLabel()).']';
                }
                if ($report->state !== null) {
                    $conditions[] = '['.Str::title($report->state->gpxLabel()).']';
                }
                if ($report->access !== null) {
                    $conditions[] = '['.Str::title($report->access->getLabel()).']';
                }
                if ($report->littered) {
                    $conditions[] = '[Littered]';
                }
                if ($report->ruined) {
                    $conditions[] = '[Ruined]';
                }

                $condition = '';

                if (count($conditions)) {
                    $condition .= implode(' ', $conditions);
                }

                $comment = $report->comment ?? '';

                return sprintf(
                    '%s (%s) %s %s',
                    $report->visited_at ? $report->visited_at->format('Y-m-d') : '[No Date]',
                    $author,
                    $condition,
                    $comment
                );
            })->join("\n");
        }

        $result = implode("\n", [$osm, $reports]);

        // Escape special XML characters to ensure the result is safe for XML
        $result = htmlspecialchars($result, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        // Remove any null bytes or other invalid XML characters
        $result = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $result);

        return $result;
    }
}
