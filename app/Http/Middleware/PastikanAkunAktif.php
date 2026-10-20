<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menutup celah akun yang dinonaktifkan atau tidak lagi memiliki salah satu dari
 * empat role (termasuk sisa role lama) walaupun sesinya masih berjalan.
 */
class PastikanAkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->bolehLogin()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun tidak aktif atau belum memiliki peran yang valid. Hubungi Admin.',
            ]);
        }

        return $next($request);
    }
}
