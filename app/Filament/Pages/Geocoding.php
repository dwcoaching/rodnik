<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\GeocodingRun;
use App\Services\Geocoding\BulkGeocoding;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Throwable;
use UnitEnum;

final class Geocoding extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $title = 'Reverse geocoding';

    protected string $view = 'filament.pages.geocoding';

    public static function canAccess(): bool
    {
        return Gate::allows('admin');
    }

    public function startRun(string $mode): void
    {
        Gate::authorize('admin');
        abort_unless(in_array($mode, BulkGeocoding::MODES, true), 422);
        $arguments = ['--queue' => true];
        if ($mode !== 'all') {
            $arguments['--'.$mode] = true;
        }

        $this->runCommand($arguments);
    }

    public function resumeRun(): void
    {
        Gate::authorize('admin');
        $this->runCommand(['--queue' => true, '--resume' => true]);
    }

    public function stopRun(): void
    {
        Gate::authorize('admin');
        if (($run = $this->activeRun()) === null) {
            return;
        }

        try {
            app(BulkGeocoding::class)->stop($run);
            Notification::make()->title('Reverse geocoding run stopped')->success()->send();
        } catch (Throwable $exception) {
            Notification::make()->title('Unable to stop run')->body($exception->getMessage())->danger()->send();
        }
    }

    public function activeRun(): ?GeocodingRun
    {
        return GeocodingRun::whereNotNull('active_key')->first();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('missing')->label('Geocode missing locations')
                ->disabled(fn () => $this->activeRun() !== null)
                ->action(fn () => $this->startRun('missing')),
            Action::make('all')->label('Recalculate all')
                ->disabled(fn () => $this->activeRun() !== null)
                ->action(fn () => $this->startRun('all')),
            Action::make('reset')->label('Clear and recalculate all')->color('danger')
                ->disabled(fn () => $this->activeRun() !== null)
                ->requiresConfirmation()
                ->modalHeading('Clear saved locations and geocode all water sources?')
                ->modalDescription('This clears the country, city, region, and geocoding metadata for every existing water source, then recalculates them in the background. The dataset is validated before anything is cleared.')
                ->modalSubmitActionLabel('Clear and recalculate')
                ->action(fn () => $this->startRun('reset')),
            Action::make('resume')->label('Resume queued or interrupted run')
                ->visible(fn () => $this->activeRun()?->canRecover() ?? false)
                ->action(fn () => $this->resumeRun()),
            Action::make('stop')->label('Stop and release run')->color('danger')
                ->visible(fn () => $this->activeRun()?->canRecover() ?? false)
                ->requiresConfirmation()
                ->modalDescription('The queued or interrupted run will be stopped. Completed geocoding is preserved, and another run can be started.')
                ->action(fn () => $this->stopRun()),
        ];
    }

    protected function getViewData(): array
    {
        return ['runs' => GeocodingRun::latest('id')->limit(10)->get()];
    }

    private function runCommand(array $arguments): void
    {
        $exitCode = Artisan::call('springs:geocode', $arguments);
        $notification = Notification::make()->title($exitCode === 0 ? 'Reverse geocoding queued' : 'Unable to start reverse geocoding')
            ->body(mb_trim(Artisan::output()));
        if ($exitCode === 0) {
            $notification->success();
        } else {
            $notification->danger();
        }
        $notification->send();
    }
}
