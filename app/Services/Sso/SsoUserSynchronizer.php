<?php

namespace App\Services\Sso;

use App\Enums\Role;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Menyalin data profil SSO ke tabel users (buat baru atau perbarui), setiap kali login SSO.
 *
 * Aturan (rencana teknis 3.1, PRD US-01):
 *   - Cari user berdasarkan NIM (mahasiswa/alumni) atau NIDN (dosen).
 *   - Selalu sinkronkan: nama, email, user_type, graduation_year, program_studi, origin_faculty_id.
 *     -> mahasiswa yang sudah lulus otomatis berubah menjadi alumni.
 *   - TIDAK PERNAH mengubah role dan faculty_id. Dosen yang ditunjuk Super Admin menjadi
 *     Admin Fakultas tetap Admin walau login lewat SSO.
 *   - Password SSO tidak disimpan. User baru diberi password acak (kolom password wajib diisi).
 */
class SsoUserSynchronizer
{
    public function sync(SsoProfile $profile): User
    {
        $column = $profile->identifierColumn();   // 'nim' atau 'nidn'
        $identifier = $profile->identifier();

        $user = User::where($column, $identifier)->first();

        if ($user === null) {
            // Akun baru: nilai yang hanya diisi SEKALI saat dibuat
            $user = new User([
                'role' => Role::EndUser,
                'is_active' => true,
                'password' => Str::random(40), // di-hash otomatis oleh cast 'hashed' di model User
            ]);
            $user->{$column} = $identifier;
        }

        $user->fill([
            'name' => $profile->name,
            'email' => $this->resolveEmail($profile, $user),
            'user_type' => $profile->userType,
            'graduation_year' => $profile->graduationYear,
            'program_studi' => $profile->programStudi ?? $user->program_studi,
        ]);

        // Fakultas asal hanya diubah jika SSO memberi kode yang dikenal; kalau tidak, nilai lama dipertahankan.
        $originFacultyId = $profile->facultyCode
            ? Faculty::where('code', $profile->facultyCode)->value('id')
            : null;
        if ($originFacultyId !== null) {
            $user->origin_faculty_id = $originFacultyId;
        }

        $user->save();

        return $user;
    }

    /**
     * Email dari SSO jika ada dan belum dipakai akun lain.
     * Kalau kosong: pakai email lama (user lama) atau {nim|nidn}@{domain pengganti}.
     */
    private function resolveEmail(SsoProfile $profile, User $user): string
    {
        $email = $profile->email ? Str::lower($profile->email) : null;

        if ($email !== null) {
            $takenByOther = User::where('email', $email)
                ->when($user->exists, fn ($q) => $q->whereKeyNot($user->getKey()))
                ->exists();

            if (! $takenByOther) {
                return $email;
            }
        }

        if ($user->exists && $user->email) {
            return $user->email;
        }

        return $profile->identifier().'@'.config('sso.email_fallback_domain');
    }
}
