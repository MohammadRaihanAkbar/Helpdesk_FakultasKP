<?php

namespace App\Services\Auth;

use App\Enums\AccountStatus;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\UserType;
use App\Exceptions\BusinessRuleException;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\AlumniRegisteredMail;
use App\Notifications\AlumniVerifiedMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * [M6] Pendaftaran alumni hidden link (US-01B) + verifikasi oleh Admin Fakultas asal.
 *
 *   register()  akun baru berstatus PENDING (belum bisa login) + kabari Admin Fakultas asal (in-app + email)
 *   approve()   Admin menyetujui -> APPROVED, alumni dikabari lewat email
 *   reject()    Admin menolak dengan alasan -> REJECTED, alumni dikabari lewat email (berisi alasan)
 *
 * Daftar ulang setelah ditolak: NIM/email yang sama boleh dipakai lagi. Akun yang ditolak tidak pernah bisa login,
 * jadi tidak punya tiket; barisnya cukup DIPAKAI ULANG (diisi data baru, kembali pending) — tidak ada akun ganda.
 *
 * Siapa yang boleh memverifikasi (Admin fakultas asal saja) dijaga UserPolicy::verifyAlumni di controller.
 */
class AlumniVerificationService
{
    /** Awalan subjek notifikasi in-app ke Admin (dipakai NotificationController untuk membuka halaman verifikasi). */
    public const REGISTERED_SUBJECT = 'Pendaftaran alumni baru';

    /**
     * @param  array{name: string, email: string, nim: string, graduation_year: int, origin_faculty_id: int, password: string}  $data
     */
    public function register(array $data): User
    {
        $user = DB::transaction(function () use ($data) {
            // Baris lama yang DITOLAK dengan NIM atau email yang sama -> dipakai ulang.
            $rejected = User::where('account_status', AccountStatus::Rejected)
                ->where(fn ($query) => $query->where('nim', $data['nim'])->orWhere('email', $data['email']))
                ->orderByRaw('CASE WHEN nim = ? THEN 0 ELSE 1 END', [$data['nim']])
                ->get();

            $user = $rejected->first() ?? new User;
            // Baris ditolak lain (mis. email cocok tetapi NIM beda) dihapus supaya email/NIM bisa dipakai baris ini.
            $rejected->skip(1)->each(fn (User $old) => $old->delete());

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'nim' => $data['nim'],
                'graduation_year' => $data['graduation_year'],
                'origin_faculty_id' => $data['origin_faculty_id'],
                'password' => $data['password'], // di-hash otomatis (cast 'hashed')
                'role' => Role::EndUser,
                'user_type' => UserType::Alumni,
                'is_active' => true,
            ]);
            $user->forceFill([
                'account_status' => AccountStatus::Pending,
                'verified_at' => null,
                'verified_by' => null,
                'rejection_reason' => null,
            ])->save();

            return $user;
        });

        $this->notifyAdmins($user);

        return $user;
    }

    public function approve(User $alumni, User $admin): User
    {
        $this->ensurePending($alumni);

        $alumni->forceFill([
            'account_status' => AccountStatus::Approved,
            'verified_at' => now(),
            'verified_by' => $admin->id,
            'rejection_reason' => null,
        ])->save();

        rescue(fn () => $alumni->notify(new AlumniVerifiedMail($alumni->load('originFaculty'))));

        return $alumni;
    }

    public function reject(User $alumni, User $admin, string $reason, ?string $registrationUrl = null): User
    {
        $this->ensurePending($alumni);

        $alumni->forceFill([
            'account_status' => AccountStatus::Rejected,
            'verified_at' => now(),
            'verified_by' => $admin->id,
            'rejection_reason' => trim($reason),
        ])->save();

        rescue(fn () => $alumni->notify(new AlumniVerifiedMail($alumni->load('originFaculty'), $registrationUrl)));

        return $alumni;
    }

    /** Admin Fakultas AKTIF di fakultas asal alumni: notifikasi lonceng + email. */
    private function notifyAdmins(User $alumni): void
    {
        rescue(function () use ($alumni) {
            $admins = User::where('role', Role::Admin)
                ->where('faculty_id', $alumni->origin_faculty_id)
                ->where('is_active', true)
                ->get();

            foreach ($admins as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'ticket_id' => null,
                    'type' => NotificationType::InApp,
                    'subject' => Str::limit(self::REGISTERED_SUBJECT.": {$alumni->name}", 197),
                    'message' => "NIM {$alumni->nim}, lulus {$alumni->graduation_year}, email {$alumni->email}. Akun belum bisa dipakai sebelum Anda setujui di menu Verifikasi Alumni.",
                    'is_read' => false,
                    'sent_at' => now(),
                ]);

                if ($admin->canReceiveEmail()) {
                    rescue(fn () => $admin->notify(new AlumniRegisteredMail($alumni)));
                }
            }
        });
    }

    private function ensurePending(User $alumni): void
    {
        if ($alumni->account_status !== AccountStatus::Pending) {
            throw new BusinessRuleException('ALREADY_VERIFIED', 'Pendaftaran ini sudah diverifikasi sebelumnya.', 409);
        }
    }
}
