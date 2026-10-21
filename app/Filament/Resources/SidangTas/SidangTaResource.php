<?php

namespace App\Filament\Resources\SidangTas;

use App\Enums\StatusSidangTa;
use App\Filament\Concerns\JalankanAksi;
use App\Filament\Resources\SidangTas\Pages\ListSidangTas;
use App\Models\Dosen;
use App\Models\Pengaturan;
use App\Models\SidangTa;
use App\Services\SidangService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
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
 * Penjadwalan sidang TA dan konfirmasi "Sidang Selesai" untuk pelaku tugasnya (bawaan: Admin).
 * Admin tidak membuka dokumen mahasiswa: panel ini hanya menampilkan ringkasan, jadwal, dan penguji.
 * Verifikasi dokumen dan persetujuan akademik terjadi sebelum sidang masuk antrean penjadwalan.
 */
class SidangTaResource extends Resource
{
    use JalankanAksi;

    protected static ?string $model = SidangTa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Tugas Akhir (TA)';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Jadwal Sidang TA';

    protected static ?string $modelLabel = 'sidang TA';

    protected static ?string $pluralModelLabel = 'sidang TA';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getEloquentQuery(): Builder
    {
        // Hanya sidang yang sudah disetujui seluruh pembimbing (atau sudah lewat tahap itu) yang menjadi urusan penjadwalan.
        return parent::getEloquentQuery()
            ->whereIn('status', [StatusSidangTa::Disetujui->value, StatusSidangTa::Dijadwalkan->value, StatusSidangTa::Selesai->value])
            ->with(['pengajuan.mahasiswa', 'pengajuan.prodi', 'pengajuan.dospem', 'pengajuan.dospem2', 'penguji.dosen']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pengajuan.mahasiswa.nama')->label('Mahasiswa')->searchable(),
                TextColumn::make('pengajuan.mahasiswa.nim')->label('NIM')->searchable(),
                TextColumn::make('pengajuan.prodi.kode')->label('Prodi'),
                TextColumn::make('pembimbing')->label('Pembimbing')
                    ->state(fn (SidangTa $record) => $record->pengajuan->pembimbings()->pluck('nama')->implode(', ')),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('jadwal_mulai')->label('Jadwal')->dateTime('d M Y H:i')->placeholder('Belum dijadwalkan')->sortable(),
                TextColumn::make('ruang')->placeholder('-'),
                TextColumn::make('daftar_penguji')->label('Penguji')
                    ->state(fn (SidangTa $record) => $record->penguji->map(fn ($x) => $x->dosen->nama)->implode(', ') ?: '-')->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    StatusSidangTa::Disetujui->value => StatusSidangTa::Disetujui->label(),
                    StatusSidangTa::Dijadwalkan->value => StatusSidangTa::Dijadwalkan->label(),
                    StatusSidangTa::Selesai->value => StatusSidangTa::Selesai->label(),
                ]),
            ])
            ->recordActions([
                Action::make('jadwalkan')
                    ->label(fn (SidangTa $record) => $record->status === StatusSidangTa::Dijadwalkan ? 'Ubah jadwal' : 'Jadwalkan')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('primary')
                    ->visible(fn (SidangTa $record) => in_array($record->status, [StatusSidangTa::Disetujui, StatusSidangTa::Dijadwalkan], true) && Gate::allows('jadwalkan', $record))
                    ->modalHeading('Jadwalkan sidang')
                    ->fillForm(fn (SidangTa $record) => [
                        'jadwal_mulai' => $record->jadwal_mulai,
                        'durasi_menit' => $record->durasi_menit ?? Pengaturan::durasiSidangMenit(),
                        'ruang' => $record->ruang,
                        'penguji' => $record->penguji->pluck('dosen_id')->all(),
                    ])
                    ->schema([
                        DateTimePicker::make('jadwal_mulai')->label('Tanggal dan jam')->seconds(false)->required(),
                        TextInput::make('durasi_menit')->label('Durasi (menit)')->numeric()->minValue(15)->maxValue(480)->required(),
                        TextInput::make('ruang')->maxLength(100),
                        Select::make('penguji')->label('Penguji (pembimbing tidak dapat dipilih)')
                            ->multiple()->required()->minItems(Pengaturan::minimalPenguji())
                            ->options(fn (SidangTa $record) => Dosen::query()->where('aktif', true)
                                ->whereNotIn('id', array_filter([$record->pengajuan->dospem_id, $record->pengajuan->dospem2_id]))
                                ->orderBy('nama')->pluck('nama', 'id')->all())
                            ->helperText('Sistem menolak jadwal yang bentrok dengan sidang lain (dosen atau ruang yang sama).'),
                    ])
                    ->action(fn (SidangTa $record, array $data) => static::jalankanAksi(
                        fn () => app(SidangService::class)->jadwalkan($record, Auth::user(), $data['jadwal_mulai'], $data['ruang'] ?? null, array_values($data['penguji']), (int) $data['durasi_menit']),
                        'Sidang dijadwalkan.',
                    )),

                Action::make('selesai')
                    ->label('Sidang selesai')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Konfirmasi bahwa sidang ini sudah selesai dilaksanakan.')
                    ->visible(fn (SidangTa $record) => $record->status === StatusSidangTa::Dijadwalkan && Gate::allows('selesai', $record))
                    ->action(fn (SidangTa $record) => static::jalankanAksi(
                        fn () => app(SidangService::class)->selesai($record, Auth::user()),
                        'Sidang dikonfirmasi selesai.',
                    )),
            ])
            ->defaultSort('jadwal_mulai', 'asc');
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
            'index' => ListSidangTas::route('/'),
        ];
    }
}
