<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar ketika sebuah aksi pada alur administrasi TA/KP tidak dapat dilakukan,
 * misalnya karena status pengajuan belum berada pada tahap yang sesuai, atau data
 * pendukungnya tidak valid (dosen pembimbing tidak aktif, dokumen wajib belum lengkap).
 * Pesannya aman ditampilkan langsung kepada pengguna.
 */
class WorkflowException extends RuntimeException
{
    //
}
