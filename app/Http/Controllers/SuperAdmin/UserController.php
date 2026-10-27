<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreDekanRequest;
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
 *   PATCH /super-admin/users/{user}/role  role   tunjuk Admin Fakultas / Dekan (atau pindah fakultas) / cabut
 *   GET   /super-admin/users/dekan/create createDekan  [M6] form akun lokal Dekan
 *   POST  /super-admin/users/dekan        storeDekan   [M6] simpan akun lokal Dekan
 *
 * Super Admin tidak bisa mengubah data diri pengguna, membuat Staff, atau mengatur spesialisasi/kategori (PRD 2.4).
 * Perubahan dari halaman ini: kolom role + faculty_id (AdminAppointmentService), dan sejak M6 satu jenis akun baru:
 * akun lokal Dekan (untuk dekan yang belum pernah login lewat SSO, jadi belum ada di daftar).
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
                ->mapWithKeys(fn (Faculty $faculty) => [$faculty->id => "{$faculty->code} · {$faculty->name}".($faculty->is_active ? '' : ' (nonaktif)')])
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

        if ($request->validated('action') === 'appoint_dekan') {
            $faculty = Faculty::findOrFail($request->validated('faculty_id'));
            $appointments->appointDekan($user, $faculty);

            return back()->with('status', "{$user->name} sekarang Dekan {$faculty->code}. Ia melihat dashboard Dekan (hanya baca) saat membuka aplikasi berikutnya.");
        }

        $label = $user->role->label();
        $appointments->revoke($user);

        return back()->with('status', "Peran {$label} {$user->name} dicabut. Akunnya kembali menjadi Pelapor; jenis pelapor dan data lain tidak berubah.");
    }

    /** [M6] Form akun lokal Dekan. */
    public function createDekan(): View
    {
        return view('superadmin.users.create-dekan', [
            'facultyOptions' => Faculty::where('is_active', true)->orderBy('id')->get()
                ->mapWithKeys(fn (Faculty $faculty) => [$faculty->id => "{$faculty->code} · {$faculty->name}"])
                ->all(),
        ]);
    }

    /** [M6] Simpan akun lokal Dekan. */
    public function storeDekan(StoreDekanRequest $request, AdminAppointmentService $appointments): RedirectResponse
    {
        $dekan = $appointments->createDekan($request->validated());
        $dekan->load('faculty');

        return redirect()->route('superadmin.users.index', ['role' => 'dekan'])
            ->with('status', "Akun Dekan {$dekan->faculty->code} untuk {$dekan->name} dibuat. Ia login dengan email {$dekan->email} dan password yang baru dibuat.");
    }
}
