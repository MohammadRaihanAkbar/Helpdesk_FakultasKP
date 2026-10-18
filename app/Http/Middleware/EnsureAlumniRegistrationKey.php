<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga hidden link registrasi alumni (rencana teknis 3.1).
 *
 * Jika ALUMNI_REGISTRATION_KEY di .env DIISI, halaman /register/alumni hanya bisa dibuka
 * dengan ?key=<kunci yang sama>. Selain itu jawabannya 404 (seolah halamannya tidak ada),
 * supaya orang yang menebak URL tidak tahu bahwa halaman ini ada.
 * Jika kosong, halaman terbuka tanpa kunci (tetap tidak ditautkan dari halaman publik).
 */
class EnsureAlumniRegistrationKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('auth.alumni_registration_key');

        if ($expected !== '') {
            // hash_equals: perbandingan waktu-konstan, mencegah menebak kunci dari lamanya respons.
            abort_unless(hash_equals($expected, (string) $request->query('key')), 404);
        }

        return $next($request);
    }
}
