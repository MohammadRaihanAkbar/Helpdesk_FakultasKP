<?php

namespace App\Services\Sso;

use RuntimeException;

/**
 * Error dari proses login SSO, dengan pesan yang langsung aman ditampilkan ke pengguna.
 *
 * Dibuat lewat "named constructor" (method static) supaya pesan & kodenya konsisten:
 *   throw SsoException::invalidCredentials();
 */
class SsoException extends RuntimeException
{
    public const INVALID_CREDENTIALS = 'SSO_INVALID_CREDENTIALS';

    public const UNREACHABLE = 'SSO_UNREACHABLE';

    public const UNSUPPORTED_ACCOUNT = 'SSO_UNSUPPORTED_ACCOUNT';

    public const INVALID_PROFILE = 'SSO_INVALID_PROFILE';

    public function __construct(string $message, public readonly string $errorCode)
    {
        parent::__construct($message);
    }

    /** Bridge menjawab 401/422 atau tidak memberi token. */
    public static function invalidCredentials(): self
    {
        return new self('NIM/NIDN atau password salah.', self::INVALID_CREDENTIALS);
    }

    /** Timeout, server mati, atau error 5xx. */
    public static function unreachable(): self
    {
        return new self('Layanan SSO kampus sedang tidak dapat dihubungi. Silakan coba beberapa saat lagi.', self::UNREACHABLE);
    }

    /** Login SSO berhasil, tetapi akunnya bukan mahasiswa/dosen/alumni (mis. tenaga kependidikan). */
    public static function unsupportedAccount(): self
    {
        return new self('Akun SSO Anda tidak terdaftar sebagai mahasiswa, alumni, atau dosen. Staff fakultas silakan login dengan email.', self::UNSUPPORTED_ACCOUNT);
    }

    /** Respons profil tidak bisa dibaca (bentuknya tidak sesuai pemetaan di config/sso.php). */
    public static function invalidProfile(string $detail = ''): self
    {
        return new self('Data profil dari SSO kampus tidak dapat dibaca. Silakan hubungi admin sistem.'.($detail ? " ({$detail})" : ''), self::INVALID_PROFILE);
    }
}
