<?php

namespace App\Models;

use App\Enums\JenisPengajuan;
use App\Enums\StatusDokumen;
use App\Enums\StatusPembimbing;
use App\Enums\StatusPengajuan;
use App\Enums\StatusTahapSurat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pengajuan extends Model
{
    protected $table = 'pengajuan';

    protected $fillable = [
        'mahasiswa_id', 'pembimbing_id', 'pembimbing_pilihan_id', 'status_pembimbing',
        'jenis', 'judul', 'deskripsi', 'instansi', 'status', 'diajukan_at',
    ];

    /** Nilai awal agar model yang baru dibuat langsung memiliki status pembimbing. */
    protected $attributes = [
        'status_pembimbing' => 'belum',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisPengajuan::class,
            'status' => StatusPengajuan::class,
            'status_pembimbing' => StatusPembimbing::class,
            'diajukan_at' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mahasiswa_id');
    }

    /** Dosen pembimbing yang sah (sudah menerima atau ditetapkan operator). Null bila belum ada. */
    public function pembimbing(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pembimbing_id');
    }

    /** Khusus TA: dosen yang dipilih mahasiswa saat pengajuan. */
    public function pembimbingPilihan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pembimbing_pilihan_id');
    }

    public function riwayatPembimbing(): HasMany
    {
        return $this->hasMany(RiwayatPembimbing::class, 'pengajuan_id')->orderBy('id');
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(DokumenPengajuan::class, 'pengajuan_id');
    }

    public function dokumenTerbaru(): HasMany
    {
        return $this->hasMany(DokumenPengajuan::class, 'pengajuan_id')->where('terbaru', true);
    }

    public function pemeriksaan(): HasMany
    {
        return $this->hasMany(Pemeriksaan::class, 'pengajuan_id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatStatus::class, 'pengajuan_id')->orderBy('id');
    }

    public function dokumenResmi(): HasMany
    {
        return $this->hasMany(DokumenResmi::class, 'pengajuan_id');
    }

    /** Surat balasan TA (semua versi, dari yang pertama). */
    public function suratBalasan(): HasMany
    {
        return $this->hasMany(SuratBalasan::class, 'pengajuan_id')->orderBy('versi');
    }

    public function arsip(): HasOne
    {
        return $this->hasOne(Arsip::class, 'pengajuan_id');
    }

    public function scopeJenis(Builder $query, JenisPengajuan $jenis): Builder
    {
        return $query->where('jenis', $jenis->value);
    }

    public function scopeStatus(Builder $query, StatusPengajuan ...$status): Builder
    {
        return $query->whereIn('status', array_map(fn (StatusPengajuan $s) => $s->value, $status));
    }

    /**
     * Pengajuan yang sudah dikirim tetapi masih membutuhkan penetapan pembimbing oleh operator:
     * belum ada pembimbing (KP), ditolak dosen (TA), atau dosen pilihan mahasiswa sudah nonaktif.
     */
    public function scopePerluPembimbing(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [StatusPengajuan::Draft->value, StatusPengajuan::Diarsipkan->value])
            ->where(function (Builder $q) {
                $q->whereIn('status_pembimbing', [StatusPembimbing::Belum->value, StatusPembimbing::Ditolak->value])
                    ->orWhere(function (Builder $w) {
                        $w->where('status_pembimbing', StatusPembimbing::Menunggu->value)
                            ->whereHas('pembimbingPilihan', fn (Builder $d) => $d->where('aktif', false));
                    });
            });
    }

    /** Status ketika alur surat TA (pengantar, balasan, SK) dapat dikerjakan: setelah operator memverifikasi, sampai diarsipkan. */
    private const STATUS_SURAT_AKTIF = [
        StatusPengajuan::MenungguDosen,
        StatusPengajuan::RevisiAkademik,
        StatusPengajuan::Disetujui,
    ];

    /**
     * Pengajuan TA yang sudah lolos verifikasi operator dan belum diarsipkan:
     * pada masa inilah surat pengantar, surat balasan, dan SK pembimbing diproses.
     */
    public function tahapSuratAktif(): bool
    {
        return $this->jenis === JenisPengajuan::Ta && in_array($this->status, self::STATUS_SURAT_AKTIF, true);
    }

    /** Alur surat ditampilkan untuk TA yang sudah melewati verifikasi (termasuk yang sudah diarsipkan). */
    public function tampilkanSuratTa(): bool
    {
        return $this->tahapSuratAktif()
            || ($this->jenis === JenisPengajuan::Ta && $this->status === StatusPengajuan::Diarsipkan);
    }

    public function suratPengantarTa(): ?DokumenResmi
    {
        return $this->dokumenResmi->firstWhere('jenis', JenisPengajuan::SURAT_PENGANTAR_TA);
    }

    public function skPembimbing(): ?DokumenResmi
    {
        return $this->dokumenResmi->firstWhere('jenis', JenisPengajuan::SK_TA);
    }

    /** Surat balasan versi terbaru, atau null bila belum pernah diunggah. */
    public function balasanTerbaru(): ?SuratBalasan
    {
        return $this->suratBalasan->firstWhere('terbaru', true);
    }

    public function suratBalasanDiterima(): bool
    {
        return $this->balasanTerbaru()?->status === StatusDokumen::Diterima;
    }

    /**
     * Alasan SK pembimbing belum boleh diterbitkan, atau null bila semua syarat terpenuhi.
     * Satu-satunya tempat aturan ini; policy, controller, dan tampilan semuanya memakainya.
     */
    public function hambatanSk(): ?string
    {
        if ($this->jenis !== JenisPengajuan::Ta) {
            return 'SK pembimbing hanya untuk Tugas Akhir.';
        }

        if ($this->skPembimbing() !== null) {
            return 'SK pembimbing sudah diterbitkan. Hapus yang lama bila ingin menggantinya.';
        }

        if ($this->status === StatusPengajuan::Diarsipkan) {
            return 'Pengajuan sudah diarsipkan.';
        }

        if ($this->suratPengantarTa() === null) {
            return 'Surat pengantar belum diterbitkan.';
        }

        $balasan = $this->balasanTerbaru();
        if ($balasan === null) {
            return 'Surat balasan belum diunggah mahasiswa.';
        }

        if ($balasan->status === StatusDokumen::Menunggu) {
            return 'Surat balasan belum diperiksa operator.';
        }

        if ($balasan->status === StatusDokumen::Revisi) {
            return 'Surat balasan perlu direvisi dan diunggah ulang oleh mahasiswa.';
        }

        if (! $this->status_pembimbing->aktif() || $this->pembimbing_id === null) {
            return 'Dosen pembimbing belum ditetapkan.';
        }

        if ($this->status !== StatusPengajuan::Disetujui) {
            return 'Pengajuan belum disetujui dosen pembimbing.';
        }

        return null;
    }

    /**
     * Tiga tahap alur surat TA beserta keadaannya, untuk tampilan (mahasiswa maupun operator).
     * Kosong untuk pengajuan yang bukan TA atau belum lolos verifikasi.
     *
     * @return list<array{kunci: string, judul: string, status: StatusTahapSurat, label: string, keterangan: string, dokumen: ?DokumenResmi, balasan: ?SuratBalasan}>
     */
    public function tahapSurat(): array
    {
        if (! $this->tampilkanSuratTa()) {
            return [];
        }

        $arsip = $this->status === StatusPengajuan::Diarsipkan;
        $pengantar = $this->suratPengantarTa();
        $balasan = $this->balasanTerbaru();
        $sk = $this->skPembimbing();

        // 1. Surat pengantar (operator)
        if ($pengantar) {
            $t1 = [StatusTahapSurat::Selesai, 'Sudah terbit', 'Nomor '.$pengantar->nomor.', tanggal '.$pengantar->tanggal->translatedFormat('d M Y').'.'];
        } elseif ($arsip) {
            $t1 = [StatusTahapSurat::Terkunci, 'Tidak tercatat', 'Tidak ada surat pengantar pada pengajuan ini.'];
        } else {
            $t1 = [StatusTahapSurat::Menunggu, 'Menunggu operator', 'Operator akademik akan menerbitkan surat pengantar.'];
        }

        // 2. Surat balasan (mahasiswa mengunggah, operator memeriksa)
        if ($balasan === null) {
            $t2 = match (true) {
                $pengantar === null => [StatusTahapSurat::Terkunci, 'Belum tersedia', 'Dapat diunggah setelah surat pengantar terbit.'],
                $arsip => [StatusTahapSurat::Terkunci, 'Tidak tercatat', 'Tidak ada surat balasan pada pengajuan ini.'],
                default => [StatusTahapSurat::Giliran, 'Menunggu unggahan Anda', 'Unduh surat pengantar, mintakan balasan dari instansi, lalu unggah surat balasannya.'],
            };
        } else {
            $t2 = match ($balasan->status) {
                StatusDokumen::Menunggu => [StatusTahapSurat::Menunggu, 'Menunggu pemeriksaan', "Surat balasan versi {$balasan->versi} sedang diperiksa operator."],
                StatusDokumen::Diterima => [StatusTahapSurat::Selesai, 'Diterima', "Surat balasan versi {$balasan->versi} diterima operator."],
                StatusDokumen::Revisi => [StatusTahapSurat::Revisi, 'Perlu revisi', $balasan->catatan ?: 'Unggah ulang surat balasan yang sudah diperbaiki.'],
            };
        }

        // 3. SK pembimbing (operator)
        if ($sk) {
            $t3 = [StatusTahapSurat::Selesai, 'Sudah terbit', 'Nomor '.$sk->nomor.', tanggal '.$sk->tanggal->translatedFormat('d M Y').'.'];
        } elseif (($hambatan = $this->hambatanSk()) === null) {
            $t3 = [StatusTahapSurat::Menunggu, 'Menunggu operator', 'Semua syarat terpenuhi. Operator akademik akan menerbitkan SK pembimbing.'];
        } else {
            $t3 = [StatusTahapSurat::Terkunci, 'Belum tersedia', $hambatan];
        }

        $susun = fn (string $kunci, string $judul, array $t, ?DokumenResmi $dokumen = null, ?SuratBalasan $b = null) => [
            'kunci' => $kunci, 'judul' => $judul, 'status' => $t[0], 'label' => $t[1], 'keterangan' => $t[2],
            'dokumen' => $dokumen, 'balasan' => $b,
        ];

        return [
            $susun('pengantar', 'Surat Pengantar', $t1, $pengantar),
            $susun('balasan', 'Surat Balasan', $t2, null, $balasan),
            $susun('sk', 'SK Pembimbing', $t3, $sk),
        ];
    }

    /** TA yang menunggu operator menerbitkan surat pengantar. */
    public function scopePerluSuratPengantar(Builder $query): Builder
    {
        return $query->where('jenis', JenisPengajuan::Ta->value)
            ->whereIn('status', array_map(fn (StatusPengajuan $s) => $s->value, self::STATUS_SURAT_AKTIF))
            ->whereDoesntHave('dokumenResmi', fn (Builder $q) => $q->where('jenis', JenisPengajuan::SURAT_PENGANTAR_TA));
    }

    /** TA yang surat balasannya (versi terbaru) menunggu diperiksa operator. */
    public function scopeBalasanMenunggu(Builder $query): Builder
    {
        return $query->where('jenis', JenisPengajuan::Ta->value)
            ->whereIn('status', array_map(fn (StatusPengajuan $s) => $s->value, self::STATUS_SURAT_AKTIF))
            ->whereHas('suratBalasan', fn (Builder $q) => $q->where('terbaru', true)->where('status', StatusDokumen::Menunggu->value));
    }

    /** Teks singkat pembimbing untuk tabel: nama dosen sah, atau keadaan terkini bila belum ada. */
    public function ringkasanPembimbing(): string
    {
        if ($this->pembimbing_id !== null) {
            return $this->pembimbing?->name ?? '-';
        }

        return match ($this->status_pembimbing) {
            StatusPembimbing::Menunggu => ($this->pembimbingPilihan?->name ?? '-').' (menunggu konfirmasi)',
            StatusPembimbing::Ditolak => 'Ditolak dosen, menunggu penetapan operator',
            default => 'Belum ditentukan',
        };
    }

    /** Penjelasan singkat tentang asal dan status dosen pembimbing. */
    public function keteranganPembimbing(): string
    {
        $pilihan = $this->pembimbingPilihan?->name ?? 'Dosen pilihan';

        return match ($this->status_pembimbing) {
            StatusPembimbing::Diterima => 'Dosen pilihan mahasiswa telah menerima permintaan pembimbing.',
            StatusPembimbing::Ditetapkan => 'Ditetapkan oleh operator akademik.',
            StatusPembimbing::Menunggu => $this->status === StatusPengajuan::Draft
                ? "{$pilihan} baru menerima permintaan setelah pengajuan dikirim."
                : "Menunggu konfirmasi dari {$pilihan}.",
            StatusPembimbing::Ditolak => "{$pilihan} menolak. Operator akademik akan menetapkan dosen pengganti.",
            default => $this->jenis === JenisPengajuan::Kp
                ? 'Ditetapkan oleh operator akademik setelah pengajuan dikirim.'
                : 'Belum ditentukan.',
        };
    }
}
