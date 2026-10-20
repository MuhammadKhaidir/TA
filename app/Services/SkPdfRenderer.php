<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Mengubah HTML SK menjadi PDF. Dipisah sebagai kelas tersendiri supaya mesin
 * PDF bisa diganti dan kegagalannya ditangani rapi (tidak membuat catatan SK palsu).
 */
class SkPdfRenderer
{
    public function render(string $html): string
    {
        if (! class_exists(Dompdf::class)) {
            throw new WorkflowException('Pustaka PDF belum terpasang. Jalankan: composer require dompdf/dompdf');
        }

        $opsi = new Options;
        $opsi->set('isRemoteEnabled', false); // template tidak boleh menarik sumber dari luar
        $opsi->set('isPhpEnabled', false);
        $opsi->set('defaultFont', 'DejaVu Serif');
        $opsi->set('chroot', [storage_path(), public_path()]);

        try {
            $dompdf = new Dompdf($opsi);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $isi = $dompdf->output();
        } catch (\Throwable $e) {
            report($e);
            throw new WorkflowException('Pembuatan PDF SK gagal. Tidak ada SK yang diterbitkan; silakan coba lagi atau periksa template.');
        }

        if ($isi === '' || ! str_starts_with($isi, '%PDF')) {
            throw new WorkflowException('Pembuatan PDF SK menghasilkan berkas tidak valid. Tidak ada SK yang diterbitkan.');
        }

        return $isi;
    }
}
