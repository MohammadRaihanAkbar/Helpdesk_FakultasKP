<?php

namespace App\Enums;

/**
 * [M6] Status akun (kolom users.account_status).
 *
 *   approved -> boleh login (SEMUA akun lama, akun SSO, Staff/Admin/Dekan, alumni yang sudah disetujui)
 *   pending  -> alumni hidden link yang baru mendaftar, menunggu verifikasi Admin Fakultas asal; belum bisa login
 *   rejected -> pendaftaran ditolak Admin (alasan di users.rejection_reason); belum bisa login, boleh daftar ulang
 *
 * Beda dengan is_active: is_active = akun DINONAKTIFKAN Admin (mis. Staff yang cuti). account_status = hasil
 * VERIFIKASI pendaftaran. Dua hal berbeda, jadi kolomnya juga dipisah.
 */
enum AccountStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu verifikasi',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }
}
