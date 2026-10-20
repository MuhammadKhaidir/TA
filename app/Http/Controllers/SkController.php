<?php

namespace App\Http\Controllers;

use App\Models\JenisSurat;
use App\Models\Pengajuan;
use App\Models\SuratPermohonan;
use App\Services\PengajuanService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** Unduhan berkas resmi (SK TA, surat hasil, template surat) lewat route terotorisasi. */
class SkController extends Controller
{
    public function unduhSk(Pengajuan $pengajuan)
    {
        Gate::authorize('view', $pengajuan);

        $sk = $pengajuan->suratKeputusan;
        abort_unless($sk && Storage::disk(PengajuanService::DISK)->exists($sk->file_path), 404, 'SK belum terbit atau berkas tidak ditemukan.');

        return Storage::disk(PengajuanService::DISK)->download($sk->file_path, 'SK-TA-'.$pengajuan->mahasiswa->nim.'.pdf');
    }

    public function unduhSurat(SuratPermohonan $surat)
    {
        Gate::authorize('view', $surat);
        abort_unless($surat->file_path && Storage::disk(PengajuanService::DISK)->exists($surat->file_path), 404, 'Surat belum tersedia.');

        return Storage::disk(PengajuanService::DISK)->download($surat->file_path, $surat->nama_file_asli ?? basename($surat->file_path));
    }

    /** Template kosong yang disediakan Admin; boleh diunduh semua pengguna yang login. */
    public function unduhTemplateSurat(JenisSurat $jenis)
    {
        abort_unless($jenis->aktif && $jenis->template_path && Storage::disk(PengajuanService::DISK)->exists($jenis->template_path), 404);

        return Storage::disk(PengajuanService::DISK)->download($jenis->template_path, $jenis->template_nama ?? basename($jenis->template_path));
    }
}
