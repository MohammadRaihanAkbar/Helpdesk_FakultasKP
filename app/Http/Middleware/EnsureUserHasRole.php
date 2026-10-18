<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware `role`: hanya meloloskan user dengan peran tertentu.
 *
 * Contoh pemakaian di routes/web.php:
 *   Route::middleware(['auth', 'role:staff'])->...
 *   Route::middleware(['auth', 'role:admin,super_admin'])->...   (salah satu peran)
 *
 * Middleware adalah "penjaga pintu" yang berjalan SEBELUM controller. Kalau syarat
 * tidak terpenuhi, request dihentikan di sini dan controller tidak pernah dipanggil.
 */
class EnsureUserHasRole
{
    /**
     * @param  string  ...$roles  nilai enum Role, mis. 'staff', 'super_admin'
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Belum login: arahkan ke halaman login (biasanya sudah dicegat middleware 'auth' lebih dulu).
        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        $allowed = array_map(fn (string $role) => Role::from($role), $roles);

        // Sudah login tapi perannya tidak sesuai: 403 Forbidden.
        abort_unless($user->hasRole(...$allowed), 403, 'Anda tidak memiliki akses ke halaman ini.');

        return $next($request);
    }
}
