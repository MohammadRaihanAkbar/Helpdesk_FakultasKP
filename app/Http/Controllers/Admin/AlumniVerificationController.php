<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Http\Requests\Admin\RejectAlumniRequest;
use App\Models\User;
use App\Services\Auth\AlumniVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * [M6] Verifikasi Alumni (/admin/verifikasi-alumni) — pendaftar alumni hidden link dari fakultas admin.
 *
 *   GET   /admin/verifikasi-alumni                 index    daftar menunggu verifikasi + riwayat 10 terakhir
 *   PATCH /admin/verifikasi-alumni/{id}/setujui    approve  akun aktif, alumni dikabari lewat email
 *   PATCH /admin/verifikasi-alumni/{id}/tolak      reject   alasan wajib, alumni dikabari lewat email
 *
 * Yang memverifikasi = Admin Fakultas ASAL alumni (users.origin_faculty_id), dijaga UserPolicy::verifyAlumni.
 */
class AlumniVerificationController extends AdminController
{
    public function index(Request $request): View
    {
        $facultyId = $this->facultyId($request);

        return view('admin.alumni.index', [
            'faculty' => $request->user()->faculty,
            'pending' => User::pendingAlumniOf($facultyId)->orderBy('created_at')->orderBy('id')->get(),
            'recent' => User::where('origin_faculty_id', $facultyId)
                ->where('user_type', 'alumni')
                ->whereIn('account_status', [AccountStatus::Approved, AccountStatus::Rejected])
                ->whereNotNull('verified_at')
                ->with('verifier')
                ->orderByDesc('verified_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function approve(Request $request, User $alumni, AlumniVerificationService $verification): RedirectResponse
    {
        $this->facultyId($request);
        Gate::authorize('verifyAlumni', $alumni);

        $verification->approve($alumni, $request->user());

        return back()->with('status', "Akun {$alumni->name} disetujui. Ia sudah bisa login dan dikabari lewat email.");
    }

    public function reject(RejectAlumniRequest $request, User $alumni, AlumniVerificationService $verification): RedirectResponse
    {
        $this->facultyId($request);
        Gate::authorize('verifyAlumni', $alumni);

        $key = (string) config('auth.alumni_registration_key');
        $verification->reject($alumni, $request->user(), $request->validated('reason'),
            route('register.alumni', array_filter(['key' => $key])));

        return back()->with('status', "Pendaftaran {$alumni->name} ditolak. Alasannya dikirim ke email pendaftar.");
    }
}
