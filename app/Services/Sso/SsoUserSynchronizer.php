<?php

namespace App\Services\Sso;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Enums\UserType;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Menyalin data profil SSO ke tabel users (buat baru atau perbarui), setiap kali login SSO.
 *
 * Aturan (rencana teknis 3.1, PRD US-01):
 *   - Cari user berdasarkan NIM (mahasiswa/alumni) atau NIP (dosen; NIDN juga dicocokkan, lihat findExisting()).
 *   - Selalu sinkronkan: nama, email, user_type, graduation_year, program_studi, origin_faculty_id.
 *     -> mahasiswa yang sudah lulus otomatis berubah menjadi alumni.
 *   - TIDAK PERNAH mengubah role dan faculty_id. Dosen yang ditunjuk Super Admin menjadi
 *     Admin Fakultas (atau Dekan, M6) tetap pada perannya walau login lewat SSO.
 *   - Password SSO tidak disimpan. User baru diberi password acak (kolom password wajib diisi).
 *   - Akun alumni hidden link yang NIM-nya ternyata ada di SSO: password lokalnya diganti acak.
 */
class SsoUserSynchronizer
{
    public function sync(SsoProfile $profile): User
    {
        $user = $this->findExisting($profile);

        if ($user === null) {
            // Akun baru: nilai yang hanya diisi SEKALI saat dibuat
            $user = new User([
                'role' => Role::EndUser,
                'is_active' => true,
                'password' => Str::random(40), // di-hash otomatis oleh cast 'hashed' di model User
            ]);
        }

        // Identitas selalu mengikuti SSO.
        if ($profile->userType === UserType::Dosen) {
            $this->syncLecturerIds($user, $profile);
        } else {
            $user->nim = $profile->nim;
        }

        // Akun ini dulu didaftarkan lewat hidden link alumni, tetapi NIM-nya ternyata dimiliki
        // pengguna SSO yang sah. Password lokal hasil registrasi dibuang (diganti acak), supaya
        // pendaftar hidden link tidak bisa lagi masuk ke akun ini lewat email/NIM + password lamanya.
        if ($user->exists && $user->isHiddenLinkAlumni()) {
            $user->password = Str::random(40);
        }

        // [M6] Login SSO berhasil = identitasnya sudah diverifikasi kampus. Kalau barisnya berasal dari pendaftaran
        // hidden link yang masih menunggu / ditolak, statusnya langsung disetujui supaya pemilik NIM yang sah bisa masuk.
        if ($user->exists && ! $user->isApproved()) {
            $user->forceFill(['account_status' => AccountStatus::Approved, 'verified_at' => now(), 'verified_by' => null, 'rejection_reason' => null]);
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
     * Cari akun lama milik orang yang sama.
     *
     * Mahasiswa/alumni: kolom nim. Dosen: utamakan NIP yang persis sama, lalu NIDN, lalu semua nomor yang
     * diketahui (NIP, NIDN, yang diketik) di kolom nip MAUPUN nidn. Langkah terakhir ini menemukan akun dari
     * M1–M4 yang masih menyimpan NIP di kolom nidn, jadi tidak ada akun kedua untuk dosen yang sama.
     */
    private function findExisting(SsoProfile $profile): ?User
    {
        if ($profile->userType !== UserType::Dosen) {
            return User::where('nim', $profile->nim)->first();
        }

        $candidates = $profile->identifierCandidates();

        return ($profile->nip ? User::where('nip', $profile->nip)->first() : null)
            ?? ($profile->nidn ? User::where('nidn', $profile->nidn)->first() : null)
            ?? User::where(fn ($query) => $query->whereIn('nip', $candidates)->orWhereIn('nidn', $candidates))->first();
    }

    /**
     * NIP ke kolom nip, NIDN ke kolom nidn (hanya kalau SSO mengirim NIDN).
     * Nomor yang tidak dikirim SSO kali ini dibiarkan (tidak dihapus), KECUALI sisa data lama:
     * NIP yang dulu tersimpan di kolom nidn dipindah, supaya satu nomor tidak tercatat dua kali.
     */
    private function syncLecturerIds(User $user, SsoProfile $profile): void
    {
        if ($profile->nip !== null) {
            $user->nip = $profile->nip;
        }

        if ($profile->nidn !== null) {
            $user->nidn = $profile->nidn;
        } elseif ($user->nidn !== null && $user->nidn === $user->nip) {
            $user->nidn = null;
        }
    }

    /**
     * Email dari SSO jika ada dan belum dipakai akun lain.
     * Kalau kosong: pakai email lama (user lama) atau {nim|nip}@{domain pengganti}.
     *
     * [M6] Email sekarang dipakai untuk MENGIRIM notifikasi, jadi aturannya dipertegas (dites di SsoUserSynchronizerTest):
     *   - setiap login, email dari Bridge /profil MENIMPA email lama (termasuk email pengganti) -> selalu yang terbaru;
     *   - email dari SSO yang tidak valid atau berdomain pengganti diabaikan;
     *   - email asli TIDAK PERNAH ditimpa email pengganti: kalau Bridge kali ini tidak mengirim email, email lama dipakai.
     * Akun yang emailnya masih email pengganti hanya menerima notifikasi in-app (User::canReceiveEmail()).
     */
    private function resolveEmail(SsoProfile $profile, User $user): string
    {
        $email = $profile->email ? Str::lower(trim($profile->email)) : null;

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false && ! User::isPlaceholderEmail($email)) {
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
