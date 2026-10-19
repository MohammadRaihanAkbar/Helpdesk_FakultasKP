<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Models\User;
use App\Services\StaffSpecialtyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Kelola Staff (/admin/staff) — US-12, F-ADM-01, F-ADM-02.
 *
 *   GET   /admin/staff               index   daftar staff fakultas + ringkasan cakupan
 *   GET   /admin/staff/create        create  form tambah staff
 *   POST  /admin/staff               store
 *   PATCH /admin/staff/{staff}/status status aktif/nonaktif (soft disable)
 *
 * Staff fakultas lain / akun yang bukan staff -> 403 (UserPolicy::manageStaff).
 */
class StaffController extends AdminController
{
    public function index(Request $request, StaffSpecialtyService $specialties): View
    {
        $facultyId = $this->facultyId($request);

        $staff = User::query()
            ->where('role', Role::Staff)
            ->where('faculty_id', $facultyId)
            ->with(['specialties' => fn ($query) => $query->orderBy('categories.id')])
            // "jumlah tiket yang sedang ditangani" = tiket berstatus Diproses dengan handler staff ini
            ->withCount(['handledTickets as active_tickets_count' => fn ($query) => $query->where('status', TicketStatus::Diproses)])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('admin.staff.index', [
            'faculty' => $request->user()->faculty,
            'staff' => $staff,
            'coverage' => $specialties->coverage($facultyId),
        ]);
    }

    public function create(Request $request): View
    {
        $this->facultyId($request);

        return view('admin.staff.create', ['faculty' => $request->user()->faculty]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $staff = User::create([
            ...$request->validated(),              // name, email, password (di-hash otomatis oleh cast 'hashed')
            'role' => Role::Staff,
            'faculty_id' => $this->facultyId($request), // otomatis fakultas admin pembuat
            'user_type' => null,
            'is_active' => true,
        ]);

        return to_route('admin.staff.specialties.edit', $staff)
            ->with('status', "Akun staff {$staff->name} berhasil dibuat. Sekarang tetapkan spesialisasinya supaya antreannya terisi.");
    }

    public function status(Request $request, User $staff): RedirectResponse
    {
        Gate::authorize('manageStaff', $staff);

        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $staff->update(['is_active' => (bool) $validated['is_active']]);

        return back()->with('status', $staff->is_active
            ? "Akun {$staff->name} diaktifkan kembali."
            : "Akun {$staff->name} dinonaktifkan. Ia tidak bisa login, tiket yang sedang diproses tetap ada.");
    }
}
