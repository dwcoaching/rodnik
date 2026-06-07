<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\User;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringTile;
use App\Models\OverpassBatch;
use App\Models\SpringRevision;
use App\Models\WateredSpringTile;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class StatsOverview extends BaseWidget
{
    protected static bool $isLazy = false;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $totalSources = Spring::count();
        $osmSources = Spring::whereNotNull('osm_node_id')
            ->orWhereNotNull('osm_way_id')
            ->count();
        $rodnikSources = Spring::whereNull('osm_node_id')
            ->whereNull('osm_way_id')
            ->count();
        $totalReports = Report::whereNull('from_osm')
            ->whereNull('hidden_at')
            ->count();

        $springsWithReports = Spring::whereHas('reports', function($query) {
            $query->visible();
        })->count();

        $userSpringUpdates = SpringRevision::where('revision_type', 'user')->count();

        $osmSpringUpdates = SpringRevision::where('revision_type', 'from_osm')->count();

        $users = User::count();
        $usersWithReports = User::whereHas('reports', function($query) {
            $query->visible();
        })->count();
        $lastOSMUpdate = OverpassBatch::latest()->first()->created_at->format('d.m.Y');
        $tilesGenerated = SpringTile::whereNotNull('generated_at')->count()
            + WateredSpringTile::whereNotNull('generated_at')->count();

        return [
            Stat::make('Water Sources', $totalSources),
            Stat::make('OSM Sources', $osmSources),
            Stat::make('Rodnik Sources', $rodnikSources),
            Stat::make('Reports', $totalReports),
            Stat::make('Springs with Reports', $springsWithReports),
            Stat::make('User Spring Updates', $userSpringUpdates),
            Stat::make('OSM Spring Updates', $osmSpringUpdates),
            Stat::make('Users', $users),
            Stat::make('Users with Reports', $usersWithReports),
            Stat::make('LastOSMUpdate', $lastOSMUpdate),
            Stat::make('Tiles Generated', $tilesGenerated)
                ->description('Out of 67 586'),
        ];
    }
}
