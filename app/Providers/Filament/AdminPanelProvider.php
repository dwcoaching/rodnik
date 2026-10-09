<?php

namespace App\Providers\Filament;

use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\Widgets;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->spa()
            ->default()
            ->id('admin')
            ->path('filament')
            ->login()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([

            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // Rendered per page rather than registered as an asset: booting the app (composer's
            // package:discover runs before npm run build on deploy) must not need the Vite manifest.
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): HtmlString => app(Vite::class)('resources/css/app.css'))
            ->navigationItems([
                NavigationItem::make('Log Viewer')
                    ->url('/' . config('log-viewer.route_path'))
                    ->icon('heroicon-o-document-text')
                    ->group('System')
                    ->sort(999)
                    ->visible(fn () => Gate::allows('viewLogViewer')),
            ]);
    }
}
