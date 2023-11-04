<?php
 
namespace App\Filament\Pages;
 
use App\Filament\Widgets\StatsOverview;
use Filament\Pages\Dashboard as BasePage;
 
class Dashboard extends BasePage
{
    protected static ?string $pollingInterval = null;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = -2;

    protected static string $view = 'filament::pages.dashboard';

    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel ?? static::$title ?? __('filament::pages/dashboard.title');
    }

    public function getColumns(): int | string | array
    {
        return 3;
    }

    public function getTitle(): string
    {
        return static::$title ?? __('filament::pages/dashboard.title');
    }

    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
        ];
    }
}
