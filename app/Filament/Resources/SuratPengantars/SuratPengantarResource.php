<?php

namespace App\Filament\Resources\SuratPengantars;

use App\Enums\StatusSuratPengantar;
use App\Filament\Concerns\JalankanAksi;
use App\Filament\Resources\SuratPengantars\Pages\ListSuratPengantars;
use App\Models\SuratPengantar;
use App\Services\SuratPengantarService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Antrean surat pengantar TA untuk pelaku tugas "memproses surat pengantar" (bawaan: Admin).
 * Admin memproses surat resmi secara offline lalu menandainya sedang diproses dan selesai; pemeriksaan
 * kelengkapan dikerjakan Operator Akademik. Pelaku sebenarnya mengikuti pengaturan hak akses [K-01],
 * dan tiap aksi dicek lewat SuratPengantarPolicy.
 */
class SuratPengantarResource extends Resource
{
    use JalankanAksi;

    protected static ?string $model = SuratPengantar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Tugas Akhir (TA)';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Surat Pengantar TA';

    protected static ?string $modelLabel = 'surat pengantar';

    protected static ?string $pluralModelLabel = 'surat pengantar TA';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getEloquentQuery(): Builder
    {
        // Draft belum dikirim mahasiswa, jadi belum menjadi pekerjaan siapa pun.
        return parent::getEloquentQuery()->where('status', '!=', StatusSuratPengantar::Draft->value)
            ->with(['pengajuan.mahasiswa', 'pengajuan.prodi']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pengajuan.mahasiswa.nama')->label('Mahasiswa')->searchable(),
                TextColumn::make('pengajuan.mahasiswa.nim')->label('NIM')->searchable(),
                TextColumn::make('pengajuan.prodi.kode')->label('Prodi'),
                TextColumn::make('instansi')->label('Instansi tujuan')
                    ->state(fn (SuratPengantar $record) => $record->data['instansi_nama'] ?? '-')->limit(40),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('nomor_surat')->label('Nomor surat')->placeholder('-'),
                TextColumn::make('diajukan_pada')->label('Diajukan')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(StatusSuratPengantar::cases())
                    ->reject(fn (StatusSuratPengantar $s) => $s === StatusSuratPengantar::Draft)
                    ->mapWithKeys(fn (StatusSuratPengantar $s) => [$s->value => $s->label()])->all()),
            ])
            ->recordActions([
                Action::make('lihat')
                    ->label('Lihat data')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading('Data surat pengantar')
                    ->modalContent(fn (SuratPengantar $record) => view('filament.surat-pengantar-detail', ['surat' => $record->load('pengajuan.dokumens.persyaratan')]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),

                Action::make('proses')
                    ->label('Proses offline')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('primary')
                    ->visible(fn (SuratPengantar $record) => $record->status === StatusSuratPengantar::Lengkap && Gate::allows('proses', $record))
                    ->schema([TextInput::make('nomor_surat')->label('Nomor surat (opsional)')->maxLength(255)])
                    ->action(fn (SuratPengantar $record, array $data) => static::jalankanAksi(
                        fn () => app(SuratPengantarService::class)->proses($record, Auth::user(), $data['nomor_surat'] ?? null),
                        'Surat pengantar ditandai sedang diproses.',
                    )),

                Action::make('selesai')
                    ->label('Selesai / diserahkan')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Tandai bahwa surat resmi sudah jadi dan diserahkan kepada mahasiswa. Mahasiswa lalu dapat mengunggah surat balasan.')
                    ->visible(fn (SuratPengantar $record) => $record->status === StatusSuratPengantar::Diproses && Gate::allows('proses', $record))
                    ->action(fn (SuratPengantar $record) => static::jalankanAksi(
                        fn () => app(SuratPengantarService::class)->selesai($record, Auth::user()),
                        'Surat pengantar selesai dan diserahkan.',
                    )),
            ])
            ->defaultSort('diajukan_pada', 'asc');
    }

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

    public static function getPages(): array
    {
        return [
            'index' => ListSuratPengantars::route('/'),
        ];
    }
}
