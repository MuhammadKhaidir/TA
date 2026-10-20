<?php

namespace App\Filament\Resources\TemplateSks;

use App\Filament\Resources\TemplateSks\Pages\CreateTemplateSk;
use App\Filament\Resources\TemplateSks\Pages\EditTemplateSk;
use App\Filament\Resources\TemplateSks\Pages\ListTemplateSks;
use App\Models\TemplateSk;
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

class TemplateSkResource extends Resource
{
    protected static ?string $model = TemplateSk::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Tugas Akhir (TA)';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Template SK TA';

    protected static ?string $modelLabel = 'template SK';

    protected static ?string $pluralModelLabel = 'template SK';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')->required()->maxLength(255),
            Toggle::make('aktif')->label('Template aktif (hanya satu yang aktif)'),
            Textarea::make('isi')->label('Isi template (HTML)')->required()->rows(22)->columnSpanFull()->extraInputAttributes(['class' => 'font-mono text-xs'])
                ->helperText('Penanda yang tersedia: {{nomor_sk}} {{tanggal}} {{kota}} {{penandatangan_nama}} {{penandatangan_jabatan}} {{nama_mahasiswa}} {{nim}} {{angkatan}} {{prodi}} {{kode_prodi}} {{judul}} {{dosen_pembimbing}} {{nip_pembimbing}} {{periode_akademik}}. Sesuaikan redaksi dengan format resmi kampus.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->searchable(),
                IconColumn::make('aktif')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([

            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTemplateSks::route('/'),
            'create' => CreateTemplateSk::route('/create'),
            'edit' => EditTemplateSk::route('/{record}/edit'),
        ];
    }
}
