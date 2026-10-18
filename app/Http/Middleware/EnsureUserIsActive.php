<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengeluarkan (logout) user yang akunnya dinonaktifkan SAAT ia masih login.
 *
 * Login sudah menolak akun nonaktif, tetapi session (apalagi "ingat saya" 7 hari) bisa
 * masih hidup ketika Admin Fakultas menonaktifkan akun Staff. Middleware ini dipasang
 * di grup "web" (bootstrap/app.php), jadi dicek di SETIAP request halaman.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['login' => 'Akun Anda sedang dinonaktifkan. Silakan hubungi Admin Fakultas.']);
        }

        return $next($request);
    }
}
