<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use App\Models\OverpassBatch;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use App\Jobs\FetchOverpassBatchImports;
use App\Jobs\ParseOverpassBatchImports;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\OverpassBatchResource\Pages;
use App\Filament\Resources\OverpassBatchResource\RelationManagers;

class OverpassBatchResource extends Resource
{
    protected static ?string $model = OverpassBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('imports_status'),
                TextColumn::make('checks_status'),
                TextColumn::make('fetch_status'),
                TextColumn::make('coverage')
                    ->url(fn (OverpassBatch $record): String => route('coverage', ['overpassBatch' => $record])),
                TextColumn::make('parse_status'),
                TextColumn::make('parsed_percentage'),
                TextColumn::make('cleanup_status'),
                TextColumn::make('created_at'),
                TextColumn::make('updated_at'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('Fetch Imports')
                    ->action(function (OverpassBatch $record): void {
                        FetchOverpassBatchImports::dispatch($record);
                    })
                    ->icon('heroicon-s-arrow-down-tray')
                    ->visible(fn (OverpassBatch $record): bool =>
                        $record->imports_status === 'created'
                        && $record->checks_status === 'created'
                        && $record->fetch_status !== 'fetched'
                    ),
                Action::make('Parse Imports')
                    ->action(function (OverpassBatch $record): void {
                        ParseOverpassBatchImports::dispatch($record);
                    })
                    ->icon('heroicon-s-calculator')
                    ->visible(fn (OverpassBatch $record): bool =>
                        $record->fetch_status === 'fetched'
                        && $record->parse_status !== 'parsed'
                    )
            ])
            ->bulkActions([

            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOverpassBatches::route('/'),
            'create' => Pages\CreateOverpassBatch::route('/create'),
            'edit' => Pages\EditOverpassBatch::route('/{record}/edit'),
        ];
    }    
}
