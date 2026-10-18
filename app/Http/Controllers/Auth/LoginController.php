<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\LoginService;
use App\Services\Sso\SsoClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login & logout. Controller dibuat tipis: validasi di LoginRequest, logika di LoginService.
 */
class LoginController extends Controller
{
    /** GET /login */
    public function create(): View
    {
        return view('auth.login', [
            'passwordResetUrl' => config('sso.password_reset_url'),
        ]);
    }

    /** POST /login */
    public function store(LoginRequest $request, LoginService $loginService): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        try {
            $user = $loginService->attempt(
                $request->validated('login'),
                $request->validated('password'),
                $request->boolean('remember'),
            );
        } catch (ValidationException $e) {
            $request->hitRateLimiter(); // gagal -> tambah hitungan percobaan
            throw $e;
        }

        $request->clearRateLimiter();

        // Ganti ID session setelah login untuk mencegah "session fixation" (PRD 6.3).
        $request->session()->regenerate();

        // intended(): kalau sebelumnya user membuka halaman terlindungi (mis. /tickets),
        // kembalikan ke sana; kalau tidak, ke dashboard sesuai perannya.
        return redirect()->intended(route($user->role->homeRoute()));
    }

    /** POST /logout */
    public function destroy(Request $request, SsoClient $sso): RedirectResponse
    {
        // Akhiri juga sesi di Bridge bila user login lewat SSO (best effort, tidak pernah melempar error).
        if ($token = $request->session()->get(LoginService::SSO_TOKEN_SESSION_KEY)) {
            $sso->logout($token);
        }

        Auth::guard('web')->logout();

        // Hapus semua data session & buat CSRF token baru.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda sudah keluar.');
    }
}
