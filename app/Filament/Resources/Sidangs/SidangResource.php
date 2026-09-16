<?php

namespace App\Filament\Resources\Sidangs;

use App\Filament\Resources\Sidangs\Pages\CreateSidang;
use App\Filament\Resources\Sidangs\Pages\EditSidang;
use App\Filament\Resources\Sidangs\Pages\ListSidangs;
use App\Filament\Resources\Sidangs\Schemas\SidangForm;
use App\Filament\Resources\Sidangs\Tables\SidangsTable;
use App\Models\Sidang;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SidangResource extends Resource
{
    protected static ?string $model = Sidang::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SidangForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SidangsTable::configure($table);
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
            'index' => ListSidangs::route('/'),
            'create' => CreateSidang::route('/create'),
            'edit' => EditSidang::route('/{record}/edit'),
        ];
    }
}
