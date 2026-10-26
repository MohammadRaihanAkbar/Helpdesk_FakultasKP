<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Form Request = class khusus validasi. Controller menerima request yang SUDAH valid,
 * jadi tidak perlu menulis $request->validate(...) di controller.
 *
 * Request ini juga mengurus pembatasan percobaan login (throttle):
 * maksimal 5 kali gagal per menit untuk kombinasi login + alamat IP (rencana teknis 3.1).
 */
class LoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 5;

    /** Halaman login boleh diakses siapa pun (tamu), jadi selalu true. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /** Nama field di pesan error (pesan umum ada di lang/id/validation.php). */
    public function attributes(): array
    {
        return [
            'login' => 'NIM / NIP / Email',
        ];
    }

    /** Rapikan input sebelum divalidasi: buang spasi di awal/akhir (sering terbawa saat copy-paste). */
    protected function prepareForValidation(): void
    {
        $this->merge(['login' => trim((string) $this->input('login'))]);
    }

    /**
     * Tolak jika sudah terlalu banyak percobaan gagal.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    /** Catat satu percobaan gagal (kedaluwarsa setelah 60 detik). */
    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), 60);
    }

    /** Login berhasil -> hitungan percobaan gagal dihapus. */
    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    /** Kunci hitungan, contoh: "2210110001|127.0.0.1". */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
