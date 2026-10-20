<?php

namespace App\Services;

use App\Enums\JenisPengajuan;
use App\Enums\StatusAdministrasi;
use App\Enums\StatusAkademik;
use App\Enums\StatusPengajuan;
use App\Exceptions\WorkflowException;
use App\Models\LogAktivitas;
use App\Models\Pengajuan;
use App\Models\Pengaturan;
use App\Models\SuratKeputusan;
use App\Models\TemplateSk;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Penerbitan SK TA langsung dari website: render template -> PDF -> simpan file
 * + metadata + riwayat -> ubah status. Semua dalam satu transaksi: bila PDF gagal
 * dibuat, tidak ada nomor terpakai dan tidak ada catatan SK.
 *
 * Tidak ada tanda tangan digital, nama pejabat, atau nomor surat karangan:
 * semuanya berasal dari Pengaturan/template yang diisi pihak kampus.
 */
class SkService
{
    public function __construct(private readonly SkPdfRenderer $renderer) {}

    /** Bagian konfigurasi yang masih harus diisi pihak kampus sebelum SK boleh terbit. */
    public function kekuranganKonfigurasi(): array
    {
        $kurang = [];
        if (! TemplateSk::aktif()) {
            $kurang[] = 'Template SK aktif';
        }
        foreach ([
            'sk.format_nomor' => 'Format nomor SK',
            'sk.kota' => 'Kota penerbitan SK',
            'sk.penandatangan_nama' => 'Nama penandatangan SK',
            'sk.penandatangan_jabatan' => 'Jabatan penandatangan SK',
        ] as $kunci => $label) {
            if (Pengaturan::ambil($kunci) === null) {
                $kurang[] = $label;
            }
        }

        return $kurang;
    }

    /** @return array<string, string> */
    public function data(Pengajuan $p, ?string $nomor = null, ?CarbonInterface $tanggal = null): array
    {
        $p->loadMissing(['mahasiswa', 'prodi', 'dospem', 'periode']);
        $tanggal ??= now();
        $belum = fn (string $label) => "[{$label} belum diatur]";

        return [
            'nomor_sk' => $nomor ?? '[nomor dibuat otomatis saat SK diterbitkan]',
            'tanggal' => $tanggal->translatedFormat('d F Y'),
            'kota' => Pengaturan::ambil('sk.kota', $belum('kota')),
            'penandatangan_nama' => Pengaturan::ambil('sk.penandatangan_nama', $belum('nama penandatangan')),
            'penandatangan_jabatan' => Pengaturan::ambil('sk.penandatangan_jabatan', $belum('jabatan penandatangan')),
            'nama_mahasiswa' => $p->mahasiswa->nama,
            'nim' => $p->mahasiswa->nim,
            'angkatan' => $p->mahasiswa->angkatan,
            'prodi' => $p->prodi->nama,
            'kode_prodi' => $p->prodi->kode,
            'judul' => (string) $p->judul,
            'dosen_pembimbing' => $p->dospem?->nama ?? $belum('dosen pembimbing'),
            'nip_pembimbing' => $p->dospem?->nip ?? '-',
            'periode_akademik' => $p->periode?->nama ?? '-',
        ];
    }

    /** Isi HTML lengkap (siap dipratinjau atau dijadikan PDF). Nilai data selalu di-escape. */
    public function renderHtml(Pengajuan $p, ?TemplateSk $template = null, ?string $nomor = null, ?CarbonInterface $tanggal = null): string
    {
        $template ??= TemplateSk::aktif();
        if (! $template) {
            throw new WorkflowException('Belum ada template SK yang aktif. Admin perlu mengaturnya terlebih dulu.');
        }

        $data = $this->data($p, $nomor, $tanggal);
        $isi = preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn (array $m) => array_key_exists($m[1], $data) ? e($data[$m[1]]) : $m[0],
            $template->isi,
        );

        return '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>SK</title>'
            .'<style>@page{margin:2.2cm 2.5cm}body{font-family:"DejaVu Serif",serif;font-size:11.5pt;line-height:1.5;color:#000}'
            .'table{border-collapse:collapse}td{vertical-align:top;padding:1px 6px 1px 0}.tengah{text-align:center}.kanan{text-align:right}'
            .'h1,h2,h3{margin:0 0 6px}</style></head><body>'.$isi.'</body></html>';
    }

    public function terbitkan(Pengajuan $pengajuan, User $operator): SuratKeputusan
    {
        $kurang = $this->kekuranganKonfigurasi();
        if ($kurang) {
            throw new WorkflowException('SK belum dapat diterbitkan. Lengkapi dulu pengaturan: '.implode(', ', $kurang).'.');
        }

        $jalur = null;

        try {
            return DB::transaction(function () use ($pengajuan, $operator, &$jalur) {
                $p = Pengajuan::query()->whereKey($pengajuan->id)->lockForUpdate()->firstOrFail();

                if (SuratKeputusan::query()->where('pengajuan_id', $p->id)->exists()) {
                    throw new WorkflowException('SK untuk pengajuan ini sudah pernah diterbitkan.');
                }
                if ($p->jenis !== JenisPengajuan::Ta
                    || $p->status !== StatusPengajuan::Disetujui
                    || $p->status_administrasi !== StatusAdministrasi::Terverifikasi
                    || $p->status_akademik !== StatusAkademik::Disetujui
                    || ! $p->dospem_id) {
                    throw new WorkflowException('SK hanya dapat diterbitkan setelah verifikasi administrasi, persetujuan akademik, dan penetapan dosen pembimbing terpenuhi.');
                }

                $template = TemplateSk::aktif();
                $tanggal = now();
                $nomor = $this->nomorBerikutnya($p, $tanggal);

                $html = $this->renderHtml($p, $template, $nomor, $tanggal);
                $pdf = $this->renderer->render($html); // gagal di sini -> transaksi dibatalkan

                $jalur = 'sk/'.$p->id.'/SK-'.Str::slug(str_replace('/', '-', $nomor)).'.pdf';

                $sk = SuratKeputusan::create([
                    'pengajuan_id' => $p->id,
                    'template_sk_id' => $template->id,
                    'nomor' => $nomor,
                    'tanggal' => $tanggal->toDateString(),
                    'file_path' => $jalur,
                    'isi_html' => $html,
                    'diterbitkan_oleh' => $operator->id,
                ]);

                if (! Storage::disk(PengajuanService::DISK)->put($jalur, $pdf)) {
                    throw new WorkflowException('Berkas SK gagal disimpan. Tidak ada SK yang diterbitkan.');
                }

                $p->status = StatusPengajuan::Selesai;
                $p->selesai_pada = $tanggal;
                $p->save();

                LogAktivitas::catat($operator, 'sk_diterbitkan', "SK TA nomor {$nomor} diterbitkan.", $p);

                return $sk;
            });
        } catch (\Throwable $e) {
            // Transaksi sudah dibatalkan; buang berkas yatim bila sempat tertulis.
            if ($jalur && Storage::disk(PengajuanService::DISK)->exists($jalur)) {
                Storage::disk(PengajuanService::DISK)->delete($jalur);
            }
            throw $e;
        }
    }

    /** Nomor urut per tahun, dikunci di dalam transaksi agar tidak ada nomor ganda atau loncat. */
    private function nomorBerikutnya(Pengajuan $p, CarbonInterface $tanggal): string
    {
        $tahun = (int) $tanggal->format('Y');

        DB::table('penomoran_sk')->insertOrIgnore(['tahun' => $tahun, 'terakhir' => 0]);
        $baris = DB::table('penomoran_sk')->where('tahun', $tahun)->lockForUpdate()->first();
        $urut = $baris->terakhir + 1;
        DB::table('penomoran_sk')->where('tahun', $tahun)->update(['terakhir' => $urut]);

        $romawi = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        $peta = [
            '{urut}' => str_pad((string) $urut, 3, '0', STR_PAD_LEFT),
            '{tahun}' => (string) $tahun,
            '{kode_prodi}' => $p->prodi->kode,
            '{bulan_romawi}' => $romawi[(int) $tanggal->format('n')],
        ];

        return strtr((string) Pengaturan::ambil('sk.format_nomor'), $peta);
    }
}
