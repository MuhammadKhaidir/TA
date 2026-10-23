<?php

namespace App\Models\Concerns;

/**
 * Dipakai model yang menyimpan berkas (kolom nama_asli, path, mime, ukuran).
 */
trait PunyaBerkas
{
    public function ukuranManusia(): string
    {
        $byte = (int) $this->ukuran;

        if ($byte >= 1048576) {
            return number_format($byte / 1048576, 1, ',', '.').' MB';
        }

        if ($byte >= 1024) {
            return number_format($byte / 1024, 0, ',', '.').' KB';
        }

        return $byte.' B';
    }
}
