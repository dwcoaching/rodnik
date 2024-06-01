<?php

namespace App\Library;

use App\Models\Report;
use App\Models\Spring;
use Illuminate\Support\Facades\Cache;

class StatisticsService
{
    static public function getSpringsCount()
    {
        if (! Cache::has('springsCount')) {
            $springsCount = Spring
                ::whereNull('hidden_at')
                ->count();

            Cache::put('springsCount', $springsCount);
        }

        return Cache::get('springsCount');
    }

    static public function getReportsCount()
    {
        if (! Cache::has('reportsCount')) {
            $reportsCount = Report
                ::whereNull('hidden_at')
                ->whereNull('from_osm')
                ->count();

            Cache::put('reportsCount', $reportsCount);
        }

        return Cache::get('reportsCount');
    }

    static public function invalidateSpringsCount()
    {
        Cache::forget('springsCount');
    }

    static public function invalidateReportsCount()
    {
        Cache::forget('reportsCount');
    }
}
