<?php

namespace App\Enums;

/**
 * Status Sidang Tugas Akhir, mengikuti Bagan Alir POS "Pendaftaran Ujian
 * Akhir Program" (No. POS 020/POS/FASILKOM/2026).
 *
 * Setiap status merepresentasikan "langkah yang baru saja selesai" sekaligus
 * menunjukkan siapa pelaksana yang harus bertindak berikutnya. Nomor langkah
 * mengacu langsung ke nomor baris pada Bagan Alir (Langkah 1 s.d. 16).
 */
enum SidangStatus: string
{
    case Diajukan = 'diajukan';
    case MenungguVerifikasiPenata = 'menunggu_verifikasi_penata';
    case MenungguProsesSk = 'menunggu_proses_sk';
    case SkTerbit = 'sk_terbit';
    case SkDiteruskanSekdep = 'sk_diteruskan_sekdep';
    case MenyiapkanAdministrasi = 'menyiapkan_administrasi';
    case MenungguInfoJadwal = 'menunggu_info_jadwal';
    case MenungguPengecekanBerkas = 'menunggu_pengecekan_berkas';
    case SiapUjian = 'siap_ujian';
    case MenungguPenjadwalanUlang = 'menunggu_penjadwalan_ulang';
    case PelaksanaanUjian = 'pelaksanaan_ujian';
    case Selesai = 'selesai';
    case Ditunda = 'ditunda';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Diajukan',
            self::MenungguVerifikasiPenata => 'Menunggu Verifikasi Penata',
            self::MenungguProsesSk => 'Menunggu Proses SK Penguji',
            self::SkTerbit => 'SK Penguji Terbit',
            self::SkDiteruskanSekdep => 'SK Diteruskan ke SekDep',
            self::MenyiapkanAdministrasi => 'Menyiapkan Administrasi Ujian',
            self::MenungguInfoJadwal => 'Menunggu Info Jadwal ke Mahasiswa',
            self::MenungguPengecekanBerkas => 'Menunggu Pengecekan Berkas',
            self::SiapUjian => 'Siap Dilaksanakan',
            self::MenungguPenjadwalanUlang => 'Menunggu Penjadwalan Ulang',
            self::PelaksanaanUjian => 'Ujian Berlangsung / Menunggu Nilai',
            self::Selesai => 'Selesai',
            self::Ditunda => 'Ditunda',
        };
    }

    /**
     * Jumlah langkah (dari 16 langkah Bagan Alir) yang sudah tuntas.
     * Dipakai untuk menggambar stepper/garis waktu.
     */
    public function langkahSelesai(): int
    {
        return match ($this) {
            self::Ditunda => 0,
            self::Diajukan => 1,
            self::MenungguVerifikasiPenata => 2,
            self::MenungguProsesSk => 4,
            self::SkTerbit => 5,
            self::SkDiteruskanSekdep => 5,
            self::MenyiapkanAdministrasi => 6,
            self::MenungguInfoJadwal => 7,
            self::MenungguPengecekanBerkas => 8,
            self::SiapUjian => 9,
            self::MenungguPenjadwalanUlang => 9,
            self::PelaksanaanUjian => 11,
            self::Selesai => 16,
        };
    }

    /**
     * Nomor langkah yang sedang berjalan / ditunggu.
     */
    public function langkahAktif(): ?int
    {
        return match ($this) {
            self::Selesai => null,
            self::Ditunda => 1,
            self::MenungguPenjadwalanUlang => 10,
            default => $this->langkahSelesai() + 1,
        };
    }

    /**
     * Nama peran (cocok dengan kolom "name" pada tabel roles) yang harus
     * bertindak selanjutnya. Null jika tidak ada tindakan lanjutan (selesai).
     */
    public function perananBerikutnya(): ?string
    {
        return match ($this) {
            self::Ditunda => 'mahasiswa',
            self::Diajukan => 'sekdep_koor_prodi',
            self::MenungguVerifikasiPenata => 'penata',
            self::MenungguProsesSk => 'pengelola_layanan',
            self::SkTerbit => 'penata',
            self::SkDiteruskanSekdep => 'sekdep_koor_prodi',
            self::MenyiapkanAdministrasi, self::MenungguInfoJadwal, self::MenungguPengecekanBerkas => 'pengadministrasi_perkantoran',
            self::SiapUjian => 'pengadministrasi_perkantoran',
            self::MenungguPenjadwalanUlang => 'sekdep_koor_prodi',
            self::PelaksanaanUjian => 'dosen_penguji',
            self::Selesai => null,
        };
    }

    public function labelPerananBerikutnya(): string
    {
        return match ($this->perananBerikutnya()) {
            'mahasiswa' => 'Mahasiswa',
            'sekdep_koor_prodi' => 'SekDep / Koor. Prodi',
            'penata' => 'Penata',
            'pengelola_layanan' => 'Pengelola Layanan',
            'pengadministrasi_perkantoran' => 'Pengadministrasi Perkantoran',
            'dosen_penguji' => 'Dosen Penguji',
            default => '-',
        };
    }

    /**
     * Kelas Tailwind untuk badge status.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Ditunda => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20',
            self::Selesai => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
            self::MenungguPenjadwalanUlang => 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/30',
            self::SiapUjian, self::PelaksanaanUjian => 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-600/20',
            default => 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-500/15',
        };
    }

    /**
     * Judul singkat 16 langkah Bagan Alir POS, dipakai untuk stepper.
     *
     * @return array<int, string>
     */
    public static function judulLangkah(): array
    {
        return [
            1 => 'Mahasiswa menyerahkan berkas persyaratan sidang (DKN & bukti kelulusan USEP)',
            2 => 'SekDep/Koor. Prodi mengusulkan jadwal ujian & tim penguji',
            3 => 'Penata memverifikasi kelulusan USEP, DKN, dan jadwal ujian',
            4 => 'Penata meneruskan usulan jadwal ke Pengelola Layanan',
            5 => 'Pengelola Layanan mengesahkan & menerbitkan SK Penguji',
            6 => 'SK Penguji didistribusikan: Penata → SekDep → Dosen Penguji',
            7 => 'Pengadministrasi Perkantoran menyiapkan dokumen administrasi ujian',
            8 => 'Informasi jadwal ujian disampaikan ke mahasiswa',
            9 => 'Pengecekan kelengkapan berkas sebelum pelaksanaan ujian',
            10 => 'Konfirmasi kehadiran dosen / penjadwalan ulang bila berhalangan',
            11 => 'Berkas ujian diserahkan ke dosen penguji pada hari-H',
            12 => 'Dosen menginput nilai ujian TA di SIMAK',
            13 => 'Pengelola Layanan memeriksa kelengkapan nilai di SIMAK',
            14 => 'Pengelola Layanan melapor ke Penata bila ada nilai belum lengkap',
            15 => 'Penata melapor ke SekDep bila nilai masih belum lengkap',
            16 => 'SekDep menghubungi dosen agar segera menginput nilai',
        ];
    }
}
