<?php

namespace App\Filament\Resources\LogAktivitas;

use App\Enums\Peran;
use App\Filament\Resources\LogAktivitas\Pages\ListLogAktivitas;
use App\Models\LogAktivitas;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class LogAktivitasResource extends Resource
{
    protected static ?string $model = LogAktivitas::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'log aktivitas';

    protected static ?string $pluralModelLabel = 'log aktivitas';

    protected static ?string $recordTitleAttribute = 'aksi';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s')->sortable(),
                TextColumn::make('user.name')->label('Pengguna')->placeholder('Sistem')->searchable(),
                TextColumn::make('peran')->badge(),
                TextColumn::make('aksi')->searchable(),
                TextColumn::make('keterangan')->limit(80)->tooltip(fn ($record) => $record->keterangan)->searchable(),
            ])
            ->filters([
                SelectFilter::make('peran')->options(collect(Peran::cases())->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all()),
            ])

            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLogAktivitas::route('/'),
        ];
    }
}
