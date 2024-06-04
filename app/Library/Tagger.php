<?php

namespace App\Library;

use App\Models\OSMTag;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringRevision;
use App\Library\StatisticsService;
use Illuminate\Support\Facades\DB;

class Tagger
{
    public static function parseTags($tags)
    {
        $tagLine = collect(explode("\n", $tags));

        return $result = $tagLine->map(function($tagString) {
            $explodedString = explode('=', $tagString);

            if (count($explodedString) >= 2 && mb_strlen(trim($explodedString[0])) > 0 && mb_strlen(trim($explodedString[1]))) {
                return [trim($explodedString[0]), trim($explodedString[1])];
            } else {
                return null;
            }
        })->filter(function($item) {
            if (is_array($item) && count($item)) {
                return true;
            }

            return false;
        });
    }
}
