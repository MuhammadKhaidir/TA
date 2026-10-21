<?php

namespace App\Filament\Resources\Pengaturans;

use App\Filament\Concerns\PunyaMenuTerfilter;
use App\Filament\Resources\Pengaturans\Pages\EditPengaturan;
use App\Filament\Resources\Pengaturans\Pages\ListPengaturans;
use App\Models\Pengaturan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PengaturanResource extends Resource
{
    use PunyaMenuTerfilter;

    protected static ?string $model = Pengaturan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'pengaturan';

    protected static ?string $pluralModelLabel = 'pengaturan';

    protected static ?string $recordTitleAttribute = 'kunci';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationItems(): array
    {
        return [
            static::itemTerfilter('Penomoran dan Penandatangan SK', 'Tugas Akhir (TA)', 'kelompok', 'sk', 3),
            static::itemTerfilter('Pengaturan Pembimbing TA', 'Tugas Akhir (TA)', 'kelompok', 'ta', 6),
            static::itemTerfilter('Pengaturan Kartu Konsultasi', 'Tugas Akhir (TA)', 'kelompok', 'kartu', 7),
            static::itemTerfilter('Pengaturan Sidang TA', 'Tugas Akhir (TA)', 'kelompok', 'sidang', 8),
            static::itemTerfilter('Pengumpulan Lampiran KP', 'Kerja Praktik (KP)', 'kelompok', 'kp', 5),
            static::itemTerfilter('Minimum Bimbingan', 'Sistem', 'kelompok', 'bimbingan', 1),
            static::itemTerfilter('Hak Akses Tugas', 'Sistem', 'kelompok', 'akses', 3),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kunci')->label('Kunci')->disabled()->dehydrated(false),
            // Pengaturan dengan pilihan tetap (mis. jalur SK, pelaku tugas) memakai pilihan agar tidak bisa salah ketik.
            Select::make('nilai')->label('Nilai')
                ->options(fn ($record) => Pengaturan::keterangan((string) $record?->kunci)[2] ?? [])
                ->visible(fn ($record) => isset(Pengaturan::keterangan((string) $record?->kunci)[2]))
                ->required(fn ($record) => isset(Pengaturan::keterangan((string) $record?->kunci)[2]))
                ->helperText(fn ($record) => Pengaturan::keterangan((string) $record?->kunci)[1] ?: null),
            TextInput::make('nilai')->label('Nilai')->maxLength(1000)
                ->visible(fn ($record) => ! isset(Pengaturan::keterangan((string) $record?->kunci)[2]))
                ->helperText(fn ($record) => Pengaturan::keterangan((string) $record?->kunci)[1] ?: null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kunci')->label('Pengaturan')->formatStateUsing(fn ($state) => Pengaturan::keterangan((string) $state)[0])->description(fn ($record) => $record->kunci),
                TextColumn::make('nilai')->placeholder('Belum diisi')->limit(60),
            ])
            ->filters([
                SelectFilter::make('kelompok')->label('Kelompok')
                    ->options(Pengaturan::KELOMPOK)
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null) ? $query->where('kunci', 'like', $data['value'].'.%') : $query),

            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('kunci');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPengaturans::route('/'),
            'edit' => EditPengaturan::route('/{record}/edit'),
        ];
    }
}
