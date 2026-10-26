<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\FacultyRequest;
use App\Models\Faculty;
use App\Services\FacultyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Master Fakultas (/super-admin/faculties) — US-17, F-SPA-01.
 *
 *   GET   /super-admin/faculties                  index   daftar + statistik ringkas per fakultas
 *   GET   /super-admin/faculties/create           create  form tambah
 *   POST  /super-admin/faculties                  store   + katalog standar otomatis tersalin
 *   GET   /super-admin/faculties/{faculty}/edit   edit
 *   PUT   /super-admin/faculties/{faculty}        update
 *   PATCH /super-admin/faculties/{faculty}/status status  tombol Aktifkan/Nonaktifkan
 *
 * Tidak ada hapus (PRD 9.2 juga tidak punya DELETE faculties): fakultas sudah punya kategori, tiket, dan akun,
 * jadi yang tidak dipakai cukup dinonaktifkan -> hilang dari form buat tiket (TicketCategoryTree).
 */
class FacultyController extends Controller
{
    public function __construct(private readonly FacultyService $faculties) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Faculty::class);

        return view('superadmin.faculties.index', [
            'faculties' => $this->faculties->overview(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Faculty::class);

        return view('superadmin.faculties.form', ['faculty' => new Faculty(['is_active' => true])]);
    }

    public function store(FacultyRequest $request): RedirectResponse
    {
        Gate::authorize('create', Faculty::class);

        $faculty = $this->faculties->create($request->validated());

        return to_route('superadmin.faculties.index')->with('status', "Fakultas {$faculty->code} ditambahkan beserta "
            .$faculty->categories()->count().' kategori dari Katalog Kategori Layanan Standar. Langkah berikutnya: tunjuk Admin Fakultasnya di menu Pengguna.');
    }

    public function edit(Faculty $faculty): View
    {
        Gate::authorize('update', $faculty);

        return view('superadmin.faculties.form', ['faculty' => $faculty]);
    }

    public function update(FacultyRequest $request, Faculty $faculty): RedirectResponse
    {
        Gate::authorize('update', $faculty);

        $this->faculties->update($faculty, $request->validated());

        return to_route('superadmin.faculties.index')->with('status', "Data fakultas {$faculty->code} disimpan.");
    }

    public function status(Request $request, Faculty $faculty): RedirectResponse
    {
        Gate::authorize('update', $faculty);

        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $faculty->update(['is_active' => (bool) $validated['is_active']]);

        return back()->with('status', $faculty->is_active
            ? "Fakultas {$faculty->code} diaktifkan. Fakultas ini kembali muncul di form buat tiket."
            : "Fakultas {$faculty->code} dinonaktifkan. Tidak muncul lagi di form buat tiket; tiket yang sudah ada tetap bisa diselesaikan Staff.");
    }
}
