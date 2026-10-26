<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateUserRoleRequest;
use App\Models\Faculty;
use App\Models\User;
use App\Services\AdminAppointmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Daftar Pengguna (/super-admin/users) — US-18, F-SPA-02, F-SPA-04.
 *
 *   GET   /super-admin/users              index  daftar SELURUH pengguna (read-only) + cari NIM/NIP/email/nama + filter peran
 *   PATCH /super-admin/users/{user}/role  role   tunjuk Admin Fakultas (atau pindah fakultas) / cabut Admin
 *
 * Super Admin tidak bisa membuat akun, mengubah data diri pengguna, membuat Staff, atau mengatur spesialisasi/
 * kategori (PRD 2.4). Satu-satunya perubahan dari halaman ini: kolom role + faculty_id (AdminAppointmentService).
 */
class UserController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $role = Role::tryFrom((string) $request->query('role'));

        $users = User::query()
            ->with(['faculty', 'originFaculty'])
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('nim', 'like', "{$q}%")
                ->orWhere('nip', 'like', "{$q}%")
                ->orWhere('nidn', 'like', "{$q}%")))
            ->when($role, fn (Builder $query, Role $role) => $query->where('role', $role))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('superadmin.users.index', [
            'users' => $users,
            'filters' => ['q' => $q, 'role' => $role],
            'facultyOptions' => Faculty::orderByDesc('is_active')->orderBy('id')->get()
                ->mapWithKeys(fn (Faculty $faculty) => [$faculty->id => "{$faculty->code} — {$faculty->name}".($faculty->is_active ? '' : ' (nonaktif)')])
                ->all(),
        ]);
    }

    public function role(UpdateUserRoleRequest $request, User $user, AdminAppointmentService $appointments): RedirectResponse
    {
        Gate::authorize('changeRole', $user);

        if ($request->validated('action') === 'appoint') {
            $faculty = Faculty::findOrFail($request->validated('faculty_id'));
            $appointments->appoint($user, $faculty);

            return back()->with('status', "{$user->name} sekarang Admin Fakultas {$faculty->code}. Ia melihat dashboard Admin saat membuka aplikasi berikutnya.");
        }

        $appointments->revoke($user);

        return back()->with('status', "Peran Admin {$user->name} dicabut. Akunnya kembali menjadi Pelapor; jenis pelapor dan data lain tidak berubah.");
    }
}
