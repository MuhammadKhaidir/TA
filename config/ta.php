<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Sistem Administrasi TA
|--------------------------------------------------------------------------
|
| Daftar di bawah adalah isian formulir dan butir checklist yang bentuknya ditentukan
| oleh ketentuan fakultas (PDF "Prompt Sistem Administrasi TA Terintegrasi (Revisi)").
| Isinya adalah CONTOH AWAL: nama kunci, label, dan jumlahnya harus dicocokkan dengan PDF.
| Mengubahnya cukup dengan mengedit file ini; kode lain membaca daftar ini apa adanya,
| dan data lama tetap aman karena disimpan sebagai pasangan kunci-nilai (JSON).
|
| Dokumen persyaratan (Kumpulan A pada surat pengantar, dokumen sidang) TIDAK diatur di sini:
| itu dikelola Admin lewat menu Persyaratan di panel /admin.
*/

return [

    /*
    | Data yang diisi mahasiswa pada surat pengantar TA.
    | Format: kunci => [label, jenis input (text|textarea|date), wajib?]
    */
    'surat_pengantar_data' => [
        'instansi_nama' => ['Nama instansi / tempat penelitian', 'text', true],
        'instansi_alamat' => ['Alamat instansi', 'textarea', true],
        'ditujukan_kepada' => ['Surat ditujukan kepada (jabatan pimpinan)', 'text', true],
        'judul_sementara' => ['Judul / topik TA (sementara)', 'text', true],
        'waktu_pelaksanaan' => ['Rencana waktu pelaksanaan', 'text', true],
        'kontak_mahasiswa' => ['Nomor kontak mahasiswa yang dapat dihubungi', 'text', true],
    ],

    /*
    | Butir checklist yang diperiksa Operator saat memvalidasi surat balasan.
    | Surat balasan baru dinyatakan Valid bila SEMUA butir dicentang.
    | Format: kunci => label
    */
    'surat_balasan_checklist' => [
        'identitas_instansi' => 'Berkop dan identitas instansi jelas',
        'ditandatangani' => 'Ditandatangani pejabat yang berwenang',
        'menyatakan_persetujuan' => 'Menyatakan instansi bersedia menerima / menyetujui',
        'identitas_mahasiswa' => 'Nama dan NIM mahasiswa sesuai',
        'tanggal_surat' => 'Tanggal surat tercantum dan masih berlaku',
    ],

    /*
    | View Blade yang dipakai untuk PDF kartu konsultasi [K-04]. Ganti ke view lain bila format resmi
    | fakultas sudah ada; datanya tetap disediakan App\Services\KartuKonsultasiService.
    */
    'kartu_view' => 'pdf.kartu-konsultasi',

];
