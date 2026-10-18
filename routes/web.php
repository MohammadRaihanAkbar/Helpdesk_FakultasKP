<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AlumniRegistrationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\EndUser;
use App\Http\Controllers\Staff;
use App\Http\Controllers\SuperAdmin;
use App\Http\Middleware\EnsureAlumniRegistrationKey;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route web — mengikuti sitemap PRD bab 7, dikelompokkan per peran.
|--------------------------------------------------------------------------
|
| Middleware yang dipakai:
|   guest        -> hanya untuk yang BELUM login (yang sudah login diarahkan ke dashboard-nya)
|   auth         -> wajib login
|   role:xxx     -> wajib punya peran tertentu (App\Http\Middleware\EnsureUserHasRole)
|   throttle:6,1 -> maksimal 6 request per menit (mencegah spam/brute force)
|
| Middleware EnsureUserIsActive dipasang otomatis di semua route web (bootstrap/app.php).
*/

// Halaman utama: tamu -> login, yang sudah login -> dashboard sesuai peran (lewat middleware guest).
Route::redirect('/', '/login');

// ---------- Tamu: login & registrasi alumni ----------
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    // Batas 5x gagal/menit per login+IP diatur di LoginRequest; throttle di sini lapisan kedua per IP.
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');

    // Hidden link alumni lulus <= 2021 (US-01B). Tidak ditautkan dari halaman publik.
    Route::middleware(EnsureAlumniRegistrationKey::class)->group(function () {
        Route::get('/register/alumni', [AlumniRegistrationController::class, 'create'])->name('register.alumni');
        Route::post('/register/alumni', [AlumniRegistrationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('register.alumni.store');
    });
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// ---------- End-User (Pelapor) ----------
Route::middleware(['auth', 'role:end_user'])->group(function () {
    Route::get('/dashboard', EndUser\DashboardController::class)->name('dashboard');
});

// ---------- Staff Fakultas ----------
Route::middleware(['auth', 'role:staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/dashboard', Staff\DashboardController::class)->name('dashboard');
});

// ---------- Admin Fakultas ----------
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', Admin\DashboardController::class)->name('dashboard');
});

// ---------- Super Admin ----------
Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->name('superadmin.')->group(function () {
    Route::get('/dashboard', SuperAdmin\DashboardController::class)->name('dashboard');
});
