<?php

namespace App\Http\Controllers\Dekan;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use Illuminate\Http\Request;

/**
 * [M6] Induk controller Dekan.
 *
 * Dekan = pimpinan SATU fakultas (users.faculty_id, diisi Super Admin). Ia HANYA memantau: dashboard statistik dan
 * laporan + ekspor fakultasnya. Tidak ada aksi tiket, Ping, kelola Staff/kategori, atau detail tiket.
 *
 * Penjagaan berlapis:
 *   1. route group 'role:dekan' (EnsureUserHasRole)  -> peran lain 403, Dekan tidak bisa membuka /admin, /staff, ...
 *   2. faculty() di bawah                            -> Dekan tanpa fakultas 403 (daripada melihat data yang salah)
 *   3. TicketPolicy tidak punya cabang Dekan         -> semua izin tiket (lihat, balas, ambil, Ping) = false
 */
abstract class DekanController extends Controller
{
    protected function faculty(Request $request): Faculty
    {
        $faculty = $request->user()->faculty;

        abort_if($faculty === null, 403, 'Akun Dekan Anda belum ditugaskan ke fakultas. Hubungi Super Admin.');

        return $faculty;
    }
}
