<?php

namespace App\Filament\Concerns;

use App\Exceptions\WorkflowException;
use Filament\Notifications\Notification;

/**
 * Menjalankan aksi workflow dari panel Admin: pelanggaran aturan (WorkflowException) tampil sebagai
 * notifikasi galat, bukan halaman 500; keberhasilan tampil sebagai notifikasi sukses.
 */
trait JalankanAksi
{
    protected static function jalankanAksi(callable $aksi, string $pesanSukses): bool
    {
        try {
            $aksi();
        } catch (WorkflowException $e) {
            Notification::make()->title('Tidak dapat diproses')->body($e->getMessage())->danger()->send();

            return false;
        }

        Notification::make()->title($pesanSukses)->success()->send();

        return true;
    }
}
