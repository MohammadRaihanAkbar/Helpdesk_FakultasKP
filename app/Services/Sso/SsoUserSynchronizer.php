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
 *   - Cari user berdasarkan NIM (mahasiswa/alumni) atau NIDN (dosen; NIP sebagai cadangan).
 *   - Selalu sinkronkan: nama, email, user_type, graduation_year, program_studi, origin_faculty_id.
 *     -> mahasiswa yang sudah lulus otomatis berubah menjadi alumni.
 *   - TIDAK PERNAH mengubah role dan faculty_id. Dosen yang ditunjuk Super Admin menjadi
 *     Admin Fakultas tetap Admin walau login lewat SSO.
 *   - Password SSO tidak disimpan. User baru diberi password acak (kolom password wajib diisi).
 *   - Akun alumni hidden link yang NIM-nya ternyata ada di SSO: password lokalnya diganti acak.
 */
class SsoUserSynchronizer
{
    public function sync(SsoProfile $profile): User
    {
        $column = $profile->identifierColumn();   // 'nim' atau 'nidn'
        $identifier = $profile->identifier();

        // Cari akun lama: utamakan yang nomornya persis sama, lalu nomor lain milik orang yang sama
        // (dosen: NIDN atau NIP, lihat SsoProfile::identifierCandidates()).
        $user = User::where($column, $identifier)->first()
            ?? User::whereIn($column, $profile->identifierCandidates())->first();

        if ($user === null) {
            // Akun baru: nilai yang hanya diisi SEKALI saat dibuat
            $user = new User([
                'role' => Role::EndUser,
                'is_active' => true,
                'password' => Str::random(40), // di-hash otomatis oleh cast 'hashed' di model User
            ]);
        }

        // Identitas selalu mengikuti SSO. Contoh: dosen yang dulu tersimpan dengan NIP
        // berganti ke NIDN begitu profil SSO-nya memuat NIDN.
        $user->{$column} = $identifier;

        // Akun ini dulu didaftarkan lewat hidden link alumni, tetapi NIM-nya ternyata dimiliki
        // pengguna SSO yang sah. Password lokal hasil registrasi dibuang (diganti acak), supaya
        // pendaftar hidden link tidak bisa lagi masuk ke akun ini lewat email/NIM + password lamanya.
        if ($user->exists && $user->isHiddenLinkAlumni()) {
            $user->password = Str::random(40);
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
