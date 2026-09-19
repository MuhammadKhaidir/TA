<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar ketika sebuah aksi pada alur POS tidak dapat dilakukan, misalnya
 * karena status sidang belum berada pada langkah yang sesuai, atau pelaku
 * tidak berwenang atas sidang tersebut (contoh: dosen yang bukan anggota
 * dewan penguji mencoba menginput nilai).
 */
class WorkflowException extends RuntimeException
{
    //
}
