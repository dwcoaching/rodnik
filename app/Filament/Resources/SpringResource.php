<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Spring;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('springs.id'),
                TextColumn::make('name'),
                TextColumn::make('longitude'),
                TextColumn::make('latitude'),
            ])
            ->filters([
                Filter::make('tags')
                    ->form([
                        Forms\Components\Textarea::make('tags')
                            ->default("tourism=camp_site\ndrinking_water=no")
                            ->live(onBlur: true)
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $tags = static::parseTags($data['tags']);

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
                Tables\Actions\EditAction::make(),
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
            'create' => Pages\CreateSpring::route('/create'),
            'edit' => Pages\EditSpring::route('/{record}/edit'),
        ];
    }    

    protected static function parseTags($tags)
    {
        $tagLine = collect(explode("\n", $tags));

        return $result = $tagLine->map(function($tagString) {
            $explodedString = explode('=', $tagString);

            if (count($explodedString) >= 2 && mb_strlen($explodedString[0]) > 0 && mb_strlen($explodedString[1])) {
                return $explodedString;
            } else {
                return null;
            }
        })->filter(function($item) {
            if (is_array($item) && count($item)) {
                return true;
            }

            return false;
        });
    }
}
