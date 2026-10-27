<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AlumniRegistrationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dekan;
use App\Http\Controllers\EndUser;
use App\Http\Controllers\FileController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\NotificationController;
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
|   throttle:xxx -> batas request per menit per IP, diatur di AppServiceProvider::configureRateLimiting()
|
| Middleware EnsureUserIsActive dipasang otomatis di semua route web (bootstrap/app.php).
*/

// [M6] Halaman depan publik: profil helpdesk + tren tiket. Tamu -> tombol "Masuk", sudah login -> "Buka Dashboard".
// (Sampai M5 halaman ini langsung dialihkan ke /login.)
Route::get('/', LandingController::class)->name('home');

// ---------- Tamu: login & registrasi alumni ----------
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    // Batas 5x gagal/menit per login+IP diatur di LoginRequest; throttle di sini lapisan kedua per IP (60/menit).
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');

    // Hidden link alumni lulus <= 2021 (US-01B). Tidak ditautkan dari halaman publik.
    Route::middleware(EnsureAlumniRegistrationKey::class)->group(function () {
        Route::get('/register/alumni', [AlumniRegistrationController::class, 'create'])->name('register.alumni');
        Route::post('/register/alumni', [AlumniRegistrationController::class, 'store'])
            ->middleware('throttle:alumni-registration') // 6x/menit per IP
            ->name('register.alumni.store');
        // [M6] Gambar captcha (SVG buatan sendiri). Setiap permintaan = soal baru; 30x/menit per IP.
        Route::get('/register/alumni/captcha', [AlumniRegistrationController::class, 'captcha'])
            ->middleware('throttle:captcha')
            ->name('register.alumni.captcha');
    });
    // [M6] Setelah mendaftar: "pendaftaran diterima, tunggu verifikasi Admin Fakultas".
    Route::get('/register/alumni/terkirim', [AlumniRegistrationController::class, 'done'])->name('register.alumni.done');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// ---------- End-User (Pelapor) ----------
Route::middleware(['auth', 'role:end_user'])->group(function () {
    Route::get('/dashboard', EndUser\DashboardController::class)->name('dashboard');

    // Tiket Saya (US-03, US-04). /tickets/new harus di atas /tickets/{ticket}, dan {ticket} hanya angka.
    Route::get('/tickets', [EndUser\TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/new', [EndUser\TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [EndUser\TicketController::class, 'store'])->middleware('throttle:tickets')->name('tickets.store');
    Route::get('/tickets/{ticket}', [EndUser\TicketController::class, 'show'])->whereNumber('ticket')->name('tickets.show');
    Route::post('/tickets/{ticket}/comments', [EndUser\TicketCommentController::class, 'store'])
        ->whereNumber('ticket')->middleware('throttle:tickets')->name('tickets.comments.store');
    // Rating CSAT tiket Selesai (US-05), satu kali per tiket.
    Route::post('/tickets/{ticket}/rating', [EndUser\RatingController::class, 'store'])
        ->whereNumber('ticket')->middleware('throttle:tickets')->name('tickets.rating.store');

    // Riwayat Tiket (US-05): Selesai & Ditolak. Detail memakai halaman yang sama dengan Tiket Saya.
    Route::get('/history', [EndUser\HistoryController::class, 'index'])->name('history.index');
    Route::get('/history/{ticket}', [EndUser\TicketController::class, 'show'])->whereNumber('ticket')->name('history.show');
});

// ---------- Berkas privat (semua peran; izin dicek per berkas di policy) ----------
Route::middleware('auth')->group(function () {
    Route::get('/attachments/{attachment}', [FileController::class, 'attachment'])->whereNumber('attachment')->name('attachments.show');
    Route::get('/proofs/{proof}', [FileController::class, 'proof'])->whereNumber('proof')->name('proofs.show');
});

// ---------- Notifikasi in-app (semua peran; tiap user hanya notifikasinya sendiri) ----------
// M4: teguran Ping untuk Staff. M5: tiket dibuat, status, balasan Staff, batas thread (F-NTF-01..05, 07; in-app saja).
Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::patch('/read-all', [NotificationController::class, 'readAll'])->name('read-all');
    Route::patch('/{notification}/read', [NotificationController::class, 'read'])->whereNumber('notification')->name('read');
});

// ---------- Staff Fakultas ----------
Route::middleware(['auth', 'role:staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/dashboard', Staff\DashboardController::class)->name('dashboard');

    // Antrean Tiket sesuai spesialisasi (US-07)
    Route::get('/queue', [Staff\QueueController::class, 'index'])->name('queue');

    // Tiket Saya + detail + aksi (US-07, US-08). {ticket} hanya angka.
    Route::controller(Staff\TicketController::class)->prefix('tickets')->name('tickets.')->whereNumber('ticket')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{ticket}', 'show')->name('show');
        Route::patch('/{ticket}/assign', 'assign')->middleware('throttle:tickets')->name('assign');
        Route::patch('/{ticket}/status', 'status')->middleware('throttle:tickets')->name('status');
    });
    Route::post('/tickets/{ticket}/comments', [Staff\TicketCommentController::class, 'store'])
        ->whereNumber('ticket')->middleware('throttle:tickets')->name('tickets.comments.store');

    // Riwayat Tiket staff (US-09). Detail memakai halaman yang sama dengan Tiket Saya.
    Route::get('/history', [Staff\HistoryController::class, 'index'])->name('history');
    Route::get('/history/{ticket}', [Staff\TicketController::class, 'show'])->whereNumber('ticket')->name('history.show');

    // [M6] Laporan hasil kerja sendiri: periode 7/14/30/90 hari atau rentang tanggal + unduh CSV/Excel.
    Route::get('/laporan', [Staff\ReportController::class, 'index'])->name('laporan');
    Route::get('/laporan/export', [Staff\ReportController::class, 'export'])->name('laporan.export');
});

// ---------- Admin Fakultas ----------
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', Admin\DashboardController::class)->name('dashboard');

    // Monitor Antrean (US-16) + detail tiket HANYA BACA + Ping/Ingatkan Staff (US-14). {ticket} hanya angka.
    Route::get('/queue', [Admin\QueueController::class, 'index'])->name('queue');
    Route::get('/tickets/{ticket}', [Admin\TicketController::class, 'show'])->whereNumber('ticket')->name('tickets.show');
    Route::get('/tickets/{ticket}/pings', [Admin\TicketController::class, 'pings'])->whereNumber('ticket')->name('tickets.pings');
    Route::post('/tickets/{ticket}/ping', [Admin\PingController::class, 'store'])
        ->whereNumber('ticket')->middleware('throttle:pings')->name('tickets.ping');

    // Laporan & Analitik + ekspor CSV/Excel (US-15)
    Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [Admin\ReportController::class, 'export'])->name('reports.export');

    // Pengaturan Layanan (US-11): katalog kategori + target SLA fakultas admin
    Route::controller(Admin\ServiceController::class)->prefix('services')->name('services.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{category}/edit', 'edit')->name('edit');
        Route::put('/{category}', 'update')->name('update');
        Route::patch('/{category}/status', 'status')->name('status');
        Route::delete('/{category}', 'destroy')->name('destroy');
    });

    // Kelola Staff (US-12) + Spesialisasi (US-13). {staff} = id user ber-peran staff.
    Route::get('/staff', [Admin\StaffController::class, 'index'])->name('staff.index');
    Route::get('/staff/create', [Admin\StaffController::class, 'create'])->name('staff.create');
    Route::post('/staff', [Admin\StaffController::class, 'store'])->name('staff.store');
    Route::patch('/staff/{staff}/status', [Admin\StaffController::class, 'status'])->name('staff.status');
    Route::get('/staff/{staff}/specialties', [Admin\StaffSpecialtyController::class, 'edit'])->name('staff.specialties.edit');
    Route::put('/staff/{staff}/specialties', [Admin\StaffSpecialtyController::class, 'update'])->name('staff.specialties.update');

    // [M6] Verifikasi pendaftaran alumni hidden link dari fakultas admin (fakultas asal alumni).
    Route::get('/verifikasi-alumni', [Admin\AlumniVerificationController::class, 'index'])->name('alumni.index');
    Route::patch('/verifikasi-alumni/{alumni}/setujui', [Admin\AlumniVerificationController::class, 'approve'])->whereNumber('alumni')->name('alumni.approve');
    Route::patch('/verifikasi-alumni/{alumni}/tolak', [Admin\AlumniVerificationController::class, 'reject'])->whereNumber('alumni')->name('alumni.reject');
});

// ---------- [M6] Dekan ----------
// Pimpinan SATU fakultas (users.faculty_id): hanya dashboard statistik + laporan & ekspor. Tidak ada aksi tiket,
// Ping, detail tiket, kelola staff/kategori. Route /admin/* dan /staff/* tetap 403 untuk Dekan (role middleware).
Route::middleware(['auth', 'role:dekan'])->prefix('dekan')->name('dekan.')->group(function () {
    Route::get('/dashboard', Dekan\DashboardController::class)->name('dashboard');
    Route::get('/laporan', [Dekan\ReportController::class, 'index'])->name('laporan');
    Route::get('/laporan/export', [Dekan\ReportController::class, 'export'])->name('laporan.export');
});

// ---------- Super Admin ----------
// Hanya master data fakultas & penunjukan Admin Fakultas / Dekan (PRD 2.4, M6). Tidak menyentuh tiket, staff, atau kategori.
Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->name('superadmin.')->group(function () {
    Route::get('/dashboard', SuperAdmin\DashboardController::class)->name('dashboard');

    // Master Fakultas (US-17, F-SPA-01). Tidak ada hapus: fakultas yang tidak dipakai cukup dinonaktifkan.
    Route::controller(SuperAdmin\FacultyController::class)->prefix('faculties')->name('faculties.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{faculty}/edit', 'edit')->whereNumber('faculty')->name('edit');
        Route::put('/{faculty}', 'update')->whereNumber('faculty')->name('update');
        Route::patch('/{faculty}/status', 'status')->whereNumber('faculty')->name('status');
    });

    // Daftar Pengguna (read-only) + tunjuk/cabut Admin Fakultas (US-18, F-SPA-02, F-SPA-04).
    // [M6] + tunjuk/cabut Dekan, dan buat akun lokal Dekan (dekan yang belum pernah login SSO).
    Route::get('/users', [SuperAdmin\UserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/role', [SuperAdmin\UserController::class, 'role'])->whereNumber('user')->name('users.role');
    Route::get('/users/dekan/create', [SuperAdmin\UserController::class, 'createDekan'])->name('users.dekan.create');
    Route::post('/users/dekan', [SuperAdmin\UserController::class, 'storeDekan'])->name('users.dekan.store');
});
