<?php

namespace App\Enums;

/**
 * Peran (role) pengguna, sama persis dengan ENUM kolom users.role di migration.
 *
 * Kenapa pakai PHP Enum, bukan string biasa?
 *   - Salah ketik ('super-admin' vs 'super_admin') langsung ketahuan sebagai error,
 *     bukan bug diam-diam yang baru muncul saat user tidak bisa masuk.
 *   - Model User meng-cast kolom role ke enum ini, jadi $user->role === Role::Admin
 *     lebih jelas dibaca daripada $user->role === 'admin'.
 *   - Label untuk UI & tujuan redirect cukup ditulis SATU kali di sini.
 */
enum Role: string
{
    case EndUser = 'end_user';
    case Staff = 'staff';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';
    /** [M6] Pimpinan fakultas: hanya memantau dashboard statistik & mengekspor laporan fakultasnya (users.faculty_id). */
    case Dekan = 'dekan';

    /** Nama peran yang ditampilkan di UI (Bahasa Indonesia). */
    public function label(): string
    {
        return match ($this) {
            self::EndUser => 'Pelapor',
            self::Staff => 'Staff Fakultas',
            self::Admin => 'Admin Fakultas',
            self::SuperAdmin => 'Super Admin',
            self::Dekan => 'Dekan',
        };
    }

    /**
     * Nama route dashboard masing-masing peran (dipakai untuk redirect setelah login).
     * Sesuai sitemap PRD bab 7.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::EndUser => 'dashboard',
            self::Staff => 'staff.dashboard',
            self::Admin => 'admin.dashboard',
            self::SuperAdmin => 'superadmin.dashboard',
            self::Dekan => 'dekan.dashboard',
        };
    }
}
