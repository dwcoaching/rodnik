<?php

namespace App\Models;

use App\Models\SpringTile;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WateredSpringTile extends SpringTile
{
    use HasFactory;

    const LIMITS = [
        '0' => 1000,
        '5' => 1000,
        '8' => 0,
    ];

    const DISK = 'watered-tiles';

    public function getRandomQuery()
    {
        $randomQuery = DB::table('springs')
            ->leftJoin('reports', 'springs.id', '=', 'reports.spring_id')
            ->select('springs.id', DB::raw('COUNT(reports.id) as reports_count'))
            ->whereNull('reports.from_osm')
            ->whereNull('reports.hidden_at')
            ->where($this->getCoordinatesFunction())
            ->groupBy('springs.id')
            ->having('reports_count', '>', 0)
            ->inRandomOrder()
            ->orderBy('reports_count', 'desc')
            ->limit($this->getLimit());

        return $randomQuery;
    }
}
