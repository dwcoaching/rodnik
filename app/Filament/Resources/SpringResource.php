<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Spring;
use App\Library\Tagger;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\SpringResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\SpringResource\RelationManagers;

class SpringResource extends Resource
{
    protected static ?string $model = Spring::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

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
                TextColumn::make('id')->url(function ($record) {
                    return route('springs.show', $record->id);
                }, shouldOpenInNewTab: true)->color('primary'),
                TextColumn::make('name'),
                TextColumn::make('type'),
                TextColumn::make('longitude'),
                TextColumn::make('latitude'),
                TextColumn::make('intermittent'),
                TextColumn::make('osm_tags')->formatStateUsing(function ($record) {
                    return $record->osm_tags->map(function ($tag) {
                        return $tag->key . '=' . $tag->value;
                    })->implode('<br>');
                })->html()
            ])
            ->recordUrl(false)
            ->filters([
                Filter::make('tags')
                    ->form([
                        Forms\Components\Textarea::make('tags')
                            ->default("tourism=camp_site\ndrinking_water=no")
                            ->live(onBlur: true)
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $tags = Tagger::parseTags($data['tags']);

                        foreach ($tags as $tag) {
                            $query->whereHas('osm_tags', function($query) use ($tag) {
                                $query
                                    ->where('key', $tag[0])
                                    ->where('value', $tag[1]);
                            });
                        }

                        return $query;
                    })
            ])
            ->actions([
                //Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
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
            'index' => Pages\ListSprings::route('/'),
            // 'create' => Pages\CreateSpring::route('/create'),
            // 'edit' => Pages\EditSpring::route('/{record}/edit'),
        ];
    }
}
