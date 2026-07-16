<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

final class WateredSpringTile extends SpringTile
{
    use HasFactory;

    public const LIMITS = [
        '0' => 1000,
        '5' => 1000,
    ];

    public const DISK = 'watered-tiles';

    public function getRandomQuery()
    {
        $randomQuery = Spring::query()
            ->leftJoin('reports', 'springs.id', '=', 'reports.spring_id')
            ->select('springs.id', DB::raw('COUNT(reports.id) as reports_count'))
            ->whereNull('reports.from_osm')
            ->whereNull('reports.hidden_at')
            ->whereNull('springs.hidden_at')
            ->whereNull('springs.redirect_to_spring_id')
            ->where($this->getCoordinatesFunction())
            ->groupBy('springs.id')
            ->having('reports_count', '>', 0)
            ->inRandomOrder()
            ->orderBy('reports_count', 'desc')
            ->limit($this->getLimit());

        return $randomQuery;
    }
}
