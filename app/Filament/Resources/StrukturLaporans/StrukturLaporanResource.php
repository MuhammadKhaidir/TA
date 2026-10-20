<?php

namespace App\Filament\Resources\StrukturLaporans;

use App\Filament\Resources\StrukturLaporans\Pages\CreateStrukturLaporan;
use App\Filament\Resources\StrukturLaporans\Pages\EditStrukturLaporan;
use App\Filament\Resources\StrukturLaporans\Pages\ListStrukturLaporans;
use App\Models\StrukturLaporan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class StrukturLaporanResource extends Resource
{
    protected static ?string $model = StrukturLaporan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Kerja Praktik (KP)';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Struktur Laporan KP';

    protected static ?string $modelLabel = 'bagian laporan KP';

    protected static ?string $pluralModelLabel = 'struktur laporan KP';

    protected static ?string $recordTitleAttribute = 'judul';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('urutan')->numeric()->required()->default(0),
            TextInput::make('judul')->required()->maxLength(255),
            Textarea::make('deskripsi')->rows(3)->columnSpanFull(),
            Toggle::make('aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('urutan')->sortable(),
                TextColumn::make('judul')->searchable(),
                IconColumn::make('aktif')->boolean(),
            ])
            ->filters([

            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('urutan');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStrukturLaporans::route('/'),
            'create' => CreateStrukturLaporan::route('/create'),
            'edit' => EditStrukturLaporan::route('/{record}/edit'),
        ];
    }
}
