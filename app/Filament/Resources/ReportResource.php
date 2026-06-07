<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Actions\Reports\TransferReportToSpringAction;
use App\Filament\Resources\ReportResource\Pages\ListReports;
use App\Library\HaversineDistance;
use App\Models\Report;
use App\Models\Spring;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

final class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string | \UnitEnum | null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return Gate::allows('admin');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('admin');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('spring_id')
                    ->label('Spring')
                    ->url(fn (Report $record): string => duo_route(['spring' => $record->spring_id]), shouldOpenInNewTab: true)
                    ->color('primary')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user_id')
                    ->label('User')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('visited_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('quality')
                    ->badge(),
                TextColumn::make('state')
                    ->badge(),
                TextColumn::make('comment')
                    ->label('Comment')
                    ->limit(80),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(null)
            ->filters([
                Filter::make('visible')
                    ->label('Visible')
                    ->default(true)
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNull('hidden_at')
                        ->whereNull('from_osm')),
            ])
            ->recordActions([
                Action::make('transferToSpring')
                    ->label('Transfer to another water source')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Report $record): string => 'Transfer report #'.$record->id)
                    ->modalDescription('Paste the target water source id. Review the distance carefully before confirming the transfer.')
                    ->modalSubmitActionLabel('Transfer report')
                    ->schema([
                        TextInput::make('target_spring_id')
                            ->label('Target water source id')
                            ->required()
                            ->integer()
                            ->live(onBlur: true),
                        Placeholder::make('distance')
                            ->label('Distance')
                            ->content(fn (Report $record, \Filament\Schemas\Components\Utilities\Get $get): HtmlString => self::distancePreview($record, $get('target_spring_id'))),
                    ])
                    ->action(function (Report $record, array $data): void {
                        $targetSpringId = $data['target_spring_id'] ?? null;
                        $report = app(TransferReportToSpringAction::class)($record, $targetSpringId);

                        Notification::make()
                            ->title('Report transferred')
                            ->body('Report #'.$report->id.' is now attached to water source #'.$report->spring_id.'.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                //
            ])
            ->paginated([10, 25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReports::route('/'),
        ];
    }

    protected static function distancePreview(Report $record, mixed $targetSpringId): HtmlString
    {
        if (! $targetSpringId) {
            return new HtmlString('Enter a target water source id.');
        }

        if (! is_numeric($targetSpringId)) {
            return new HtmlString('<span class="text-danger-600">Target id must be numeric.</span>');
        }

        if ((int) $record->spring_id === (int) $targetSpringId) {
            return new HtmlString('<span class="text-danger-600">Report is already attached to this water source.</span>');
        }

        $record->loadMissing('spring');
        $target = Spring::find($targetSpringId);

        if (! $target) {
            return new HtmlString('<span class="text-danger-600">Target water source was not found.</span>');
        }

        if ($target->hidden_at) {
            return new HtmlString('<span class="text-danger-600">Target water source is hidden.</span>');
        }

        if ($target->redirect_to_spring_id) {
            return new HtmlString('<span class="text-danger-600">Target water source is redirected. Use the final water source instead.</span>');
        }

        $distance = app(HaversineDistance::class);
        $meters = $distance->metersBetweenSprings($record->spring, $target);

        if ($meters === null) {
            return new HtmlString('<span class="text-danger-600">Distance cannot be calculated because one source has missing coordinates.</span>');
        }

        $class = $meters > 100 ? 'text-danger-600 font-semibold' : 'text-success-600';
        $label = e($distance->formatMeters($meters));

        return new HtmlString('<span class="'.$class.'">'.$label.'</span>');
    }
}
