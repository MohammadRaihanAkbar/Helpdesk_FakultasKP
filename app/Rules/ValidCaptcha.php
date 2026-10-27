<?php

namespace App\Rules;

use App\Services\Auth\Captcha;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * [M6] Aturan validasi "jawaban captcha benar" (lihat App\Services\Auth\Captcha).
 *
 * Pemakaian di FormRequest: 'captcha' => ['required', new ValidCaptcha('alumni')]
 * Captcha bisa dimatikan HANYA untuk tes otomatis lewat config helpdesk.captcha.enabled (phpunit.xml).
 */
class ValidCaptcha implements ValidationRule
{
    public const MESSAGE = 'Jawaban captcha salah atau sudah kedaluwarsa. Hitung soal yang baru, lalu coba lagi.';

    public function __construct(private readonly string $form) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(Captcha::class)->check($this->form, $value)) {
            $fail(self::MESSAGE);
        }
    }
}
