<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mahasiswa</title>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; background:#f4f6f9; font-family:Arial,sans-serif; color:#1f2937; }
        nav { background:#111827; color:white; padding:18px 7%; display:flex; justify-content:space-between; align-items:center; }
        nav strong { font-size:20px; }
        button { border:0; background:#dc2626; color:white; padding:9px 14px; border-radius:7px; cursor:pointer; }
        main { width:min(1100px,86%); margin:35px auto; }
        .welcome { background:white; border-radius:15px; padding:28px; box-shadow:0 8px 25px rgba(0,0,0,.05); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:18px; margin-top:22px; }
        .item { background:white; padding:22px; border-radius:12px; }
        .item span { display:block; color:#6b7280; font-size:13px; margin-bottom:8px; }
        .item strong { font-size:18px; }
    </style>
</head>
<body>
<nav>
    <strong>Dashboard Mahasiswa</strong>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Keluar</button>
    </form>
</nav>
<main>
    <section class="welcome">
        <h1>Halo, {{ $mahasiswa?->nama ?? auth()->user()->name }}!</h1>
        <p>Selamat datang di sistem informasi tugas akhir.</p>
    </section>

    <section class="grid">
        <div class="item"><span>NIM</span><strong>{{ $mahasiswa?->nim ?? '-' }}</strong></div>
        <div class="item"><span>Program Studi</span><strong>{{ $mahasiswa?->prodi ?? '-' }}</strong></div>
        <div class="item"><span>Angkatan</span><strong>{{ $mahasiswa?->angkatan ?? '-' }}</strong></div>
        <div class="item"><span>Status Akun</span><strong>Mahasiswa</strong></div>
    </section>
</main>
</body>
</html>
