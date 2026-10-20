<?php

namespace App\Filament\Resources\PeriodeAkademiks;

use App\Filament\Resources\PeriodeAkademiks\Pages\CreatePeriodeAkademik;
use App\Filament\Resources\PeriodeAkademiks\Pages\EditPeriodeAkademik;
use App\Filament\Resources\PeriodeAkademiks\Pages\ListPeriodeAkademiks;
use App\Models\PeriodeAkademik;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PeriodeAkademikResource extends Resource
{
    protected static ?string $model = PeriodeAkademik::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $modelLabel = 'periode akademik';

    protected static ?string $pluralModelLabel = 'periode akademik';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')->label('Nama periode')->placeholder('Ganjil 2026/2027')->required()->maxLength(255)->unique(ignoreRecord: true),
            Toggle::make('aktif')->label('Periode berjalan (dipakai pengajuan baru)'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->searchable()->sortable(),
                IconColumn::make('aktif')->boolean(),
            ])
            ->filters([

            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeriodeAkademiks::route('/'),
            'create' => CreatePeriodeAkademik::route('/create'),
            'edit' => EditPeriodeAkademik::route('/{record}/edit'),
        ];
    }
}
