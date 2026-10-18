<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AlumniRegistrationRequest;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Registrasi alumni lulus <= 2021 lewat hidden link (US-01B).
 *
 * Halaman ini tidak ditautkan dari halaman publik mana pun. Jika ALUMNI_REGISTRATION_KEY diisi,
 * URL wajib memakai ?key=... (dijaga middleware EnsureAlumniRegistrationKey).
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

    /** POST /register/alumni */
    public function store(AlumniRegistrationRequest $request): RedirectResponse
    {
        // Hanya field yang sudah divalidasi yang dipakai (validated()), bukan $request->all().
        $data = $request->validated();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'nim' => $data['nim'],
            'graduation_year' => $data['graduation_year'],
            'origin_faculty_id' => $data['origin_faculty_id'],
            'password' => $data['password'], // di-hash otomatis (cast 'hashed')
            'role' => Role::EndUser,
            'user_type' => UserType::Alumni,
            'is_active' => true,
        ]);

        return redirect()->route('login')
            ->with('status', 'Registrasi alumni berhasil. Silakan login dengan email dan password Anda.');
    }
}
