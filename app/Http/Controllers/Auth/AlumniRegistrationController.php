<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AlumniRegistrationRequest;
use App\Models\Faculty;
use App\Services\Auth\AlumniVerificationService;
use App\Services\Auth\Captcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Registrasi alumni lulus <= 2021 lewat hidden link (US-01B).
 *
 * Halaman ini tidak ditautkan dari halaman publik mana pun. Jika ALUMNI_REGISTRATION_KEY diisi,
 * URL wajib memakai ?key=... (dijaga middleware EnsureAlumniRegistrationKey).
 *
 * [M6]
 *   - captcha buatan sendiri (gambar SVG dari route register.alumni.captcha, lihat App\Services\Auth\Captcha);
 *   - akun baru MENUNGGU VERIFIKASI Admin Fakultas asal, belum bisa login (AlumniVerificationService);
 *   - setelah daftar -> halaman "pendaftaran diterima" (register.alumni.done), bukan langsung ke login.
 */
class AlumniRegistrationController extends Controller
{
    /** GET /register/alumni */
    public function create(Request $request): View
    {
        return view('auth.register-alumni', [
            'faculties' => Faculty::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'key' => $request->query('key'), // dibawa ke action form supaya POST juga lolos penjagaan kunci
        ]);
    }

    /**
     * GET /register/alumni/captcha — gambar soal captcha (SVG). Setiap permintaan membuat soal BARU
     * (tombol "Ganti soal" cukup memuat ulang gambar ini). no-store: browser tidak boleh menyimpan gambar lama.
     */
    public function captcha(Captcha $captcha): Response
    {
        return response($captcha->generate('alumni'), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /** POST /register/alumni */
    public function store(AlumniRegistrationRequest $request, AlumniVerificationService $verification): RedirectResponse
    {
        // Hanya field yang sudah divalidasi yang dipakai (validated()), bukan $request->all().
        $user = $verification->register($request->safe()->except(['captcha', 'password_confirmation']));

        return redirect()->route('register.alumni.done')
            ->with('registered', ['name' => $user->name, 'email' => $user->email, 'faculty' => $user->originFaculty?->name]);
    }

    /** GET /register/alumni/terkirim — "pendaftaran diterima, tunggu verifikasi". */
    public function done(Request $request): View
    {
        return view('auth.register-alumni-done', ['registered' => $request->session()->get('registered')]);
    }
}
