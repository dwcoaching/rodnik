<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringTile;
use App\Models\OverpassBatch;
use App\Models\WateredSpringTile;
use Filament\Widgets\StatsOverviewWidget\Card;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class StatsOverview extends BaseWidget
{
    protected function getColumns(): int
    {
        return 3;
    }

    protected function getCards(): array
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
        $users = User::count();
        $usersWithReports = User::whereHas('reports', function($query) {
            $query->visible();
        })->count();
        $lastOSMUpdate = OverpassBatch::latest()->first()->created_at->format('d.m.Y');
        $tilesGenerated = SpringTile::whereNotNull('generated_at')->count()
            + WateredSpringTile::whereNotNull('generated_at')->count();

        return [
            Card::make('Water Sources', $totalSources),
            Card::make('OSM Sources', $osmSources),
            Card::make('Rodnik Sources', $rodnikSources),
            Card::make('Reports', $totalReports),
            Card::make('Spring with Reports', $springsWithReports),
            Card::make('Users', $users),
            Card::make('Users with Reports', $usersWithReports),
            Card::make('LastOSMUpdate', $lastOSMUpdate),
            Card::make('Tiles Generated', $tilesGenerated)
                ->description('Out of 67 586'),
        ];
    }
}
