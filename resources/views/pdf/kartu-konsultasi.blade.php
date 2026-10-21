{{--
    Template kartu konsultasi TA (PDF, dirender dompdf). Format resmi belum dipastikan fakultas [K-04]:
    ganti isi berkas ini (atau arahkan config('ta.kartu_view') ke view lain) tanpa mengubah kode apa pun.
    Variabel: $pengajuan, $mahasiswa, $prodi, $pembimbing1, $pembimbing2, $sk, $bimbingans, $dicetak.
    Gunakan hanya CSS sederhana: dompdf tidak mendukung flexbox/grid.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kartu Konsultasi Tugas Akhir</title>
<style>
    @page { margin: 2cm 2cm 2cm 2cm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5pt; color: #111; }
    h1 { font-size: 14pt; text-align: center; margin: 0 0 2pt; text-transform: uppercase; }
    .sub { text-align: center; font-size: 10pt; margin-bottom: 14pt; color: #444; }
    table.info { width: 100%; border-collapse: collapse; margin-bottom: 12pt; }
    table.info td { padding: 2pt 4pt; vertical-align: top; }
    table.info td.k { width: 28%; }
    table.info td.t { width: 2%; }
    table.log { width: 100%; border-collapse: collapse; }
    table.log th, table.log td { border: 0.6pt solid #333; padding: 4pt 5pt; vertical-align: top; font-size: 9.5pt; }
    table.log th { background: #eee; text-align: left; }
    .kosong { text-align: center; color: #666; padding: 14pt 0; }
    .ttd { width: 100%; margin-top: 24pt; border-collapse: collapse; }
    .ttd td { width: 50%; text-align: center; vertical-align: top; padding-top: 4pt; }
    .jarak { height: 54pt; }
</style>
</head>
<body>
    <h1>Kartu Konsultasi Tugas Akhir</h1>
    <div class="sub">{{ $prodi->nama }}</div>

    <table class="info">
        <tr><td class="k">Nama</td><td class="t">:</td><td>{{ $mahasiswa->nama }}</td></tr>
        <tr><td class="k">NIM</td><td class="t">:</td><td>{{ $mahasiswa->nim }}</td></tr>
        <tr><td class="k">Judul Tugas Akhir</td><td class="t">:</td><td>{{ $pengajuan->judul ?: '-' }}</td></tr>
        <tr><td class="k">Pembimbing 1</td><td class="t">:</td><td>{{ $pembimbing1?->nama ?? '-' }}</td></tr>
        <tr><td class="k">Pembimbing 2</td><td class="t">:</td><td>{{ $pembimbing2?->nama ?? '-' }}</td></tr>
        <tr><td class="k">Nomor SK</td><td class="t">:</td><td>{{ $sk?->nomor ?? '-' }}</td></tr>
    </table>

    <table class="log">
        <thead>
            <tr>
                <th style="width:5%">No</th>
                <th style="width:15%">Tanggal</th>
                <th style="width:28%">Topik</th>
                <th>Catatan / tindak lanjut</th>
                <th style="width:15%">Validasi dosen</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($bimbingans as $b)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $b->tanggal->format('d/m/Y') }}</td>
                <td>{{ $b->topik }}</td>
                <td>
                    {{ $b->catatan }}
                    @if ($b->tindak_lanjut)<br><em>Tindak lanjut:</em> {{ $b->tindak_lanjut }}@endif
                </td>
                <td>{{ $b->status_validasi->label() }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="kosong">Belum ada bimbingan yang tercatat.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table class="ttd">
        <tr>
            <td>Pembimbing 1<div class="jarak"></div>{{ $pembimbing1?->nama ?? '........................' }}</td>
            <td>Pembimbing 2<div class="jarak"></div>{{ $pembimbing2?->nama ?? '........................' }}</td>
        </tr>
    </table>

    <p style="font-size:8.5pt;color:#666;margin-top:18pt;">Dicetak dari sistem pada {{ $dicetak->translatedFormat('d F Y H:i') }}.</p>
</body>
</html>
