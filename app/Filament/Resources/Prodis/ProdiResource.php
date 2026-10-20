<?php

namespace App\Filament\Resources\Prodis;

use App\Filament\Resources\Prodis\Pages\CreateProdi;
use App\Filament\Resources\Prodis\Pages\EditProdi;
use App\Filament\Resources\Prodis\Pages\ListProdis;
use App\Models\Prodi;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ProdiResource extends Resource
{
    protected static ?string $model = Prodi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $modelLabel = 'program studi';

    protected static ?string $pluralModelLabel = 'program studi';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode')->label('Kode')->required()->maxLength(10)->unique(ignoreRecord: true),
            TextInput::make('nama')->label('Nama program studi')->required()->maxLength(255)->unique(ignoreRecord: true),
            Toggle::make('aktif')->label('Aktif (dapat dipilih saat registrasi)')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->searchable()->sortable(),
                TextColumn::make('nama')->searchable()->sortable(),
                IconColumn::make('aktif')->boolean(),
                TextColumn::make('mahasiswas_count')->counts('mahasiswas')->label('Mahasiswa'),
            ])
            ->filters([
                SelectFilter::make('aktif')->options([1 => 'Aktif', 0 => 'Nonaktif']),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('kode');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProdis::route('/'),
            'create' => CreateProdi::route('/create'),
            'edit' => EditProdi::route('/{record}/edit'),
        ];
    }
}
