<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Induk semua controller Admin Fakultas.
 *
 * Semua halaman admin bekerja untuk SATU fakultas: users.faculty_id milik admin yang login.
 * Admin yang belum ditugaskan ke fakultas (faculty_id NULL) tidak bisa membuka apa pun (403),
 * daripada melihat data yang salah.
 */
abstract class AdminController extends Controller
{
    protected function facultyId(Request $request): int
    {
        $facultyId = $request->user()->faculty_id;

        abort_if($facultyId === null, 403, 'Akun Admin Anda belum ditugaskan ke fakultas. Hubungi Super Admin.');

        return $facultyId;
    }
}
