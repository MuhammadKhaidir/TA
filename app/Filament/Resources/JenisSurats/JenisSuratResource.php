<?php

namespace App\Filament\Resources\JenisSurats;

use App\Filament\Resources\JenisSurats\Pages\CreateJenisSurat;
use App\Filament\Resources\JenisSurats\Pages\EditJenisSurat;
use App\Filament\Resources\JenisSurats\Pages\ListJenisSurats;
use App\Models\JenisSurat;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
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

class JenisSuratResource extends Resource
{
    protected static ?string $model = JenisSurat::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Kerja Praktik (KP)';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Jenis Surat KP';

    protected static ?string $modelLabel = 'jenis surat KP';

    protected static ?string $pluralModelLabel = 'jenis surat KP';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('nama')->required()->maxLength(255),
            Textarea::make('deskripsi')->rows(2)->columnSpanFull(),
            Textarea::make('persyaratan')->label('Persyaratan pengajuan surat (teks)')->rows(2)->columnSpanFull(),
            FileUpload::make('template_path')->label('Template surat (opsional, untuk diunduh mahasiswa)')->disk('local')->directory('template-surat')->visibility('private')->storeFileNamesIn('template_nama')->columnSpanFull(),
            Toggle::make('aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->searchable(),
                TextColumn::make('nama')->searchable(),
                IconColumn::make('aktif')->boolean(),
            ])
            ->filters([

            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('nama');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJenisSurats::route('/'),
            'create' => CreateJenisSurat::route('/create'),
            'edit' => EditJenisSurat::route('/{record}/edit'),
        ];
    }
}
