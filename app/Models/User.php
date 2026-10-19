<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\UserType;
use App\Http\Requests\Auth\AlumniRegistrationRequest;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\RoutesNotifications;

/**
 * Tabel 2: users — satu tabel untuk 4 peran (end_user, staff, admin, super_admin).
 *
 * Dua kolom fakultas dengan arti berbeda (lihat migration add_faculty_id_to_users_table):
 *   - origin_faculty_id -> fakultas ASAL pelapor      -> relasi originFaculty()
 *   - faculty_id        -> fakultas TEMPAT BERTUGAS   -> relasi faculty()
 */
#[Fillable([
    'name', 'email', 'password',
    'nim', 'nidn', 'program_studi', 'avatar_url',
    'role', 'user_type', 'graduation_year',
    'origin_faculty_id', 'faculty_id', 'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /**
     * Kenapa RoutesNotifications, bukan Notifiable?
     *
     * Notifiable = RoutesNotifications + HasDatabaseNotifications. Bagian kedua menambah
     * relasi notifications() yang mengharapkan tabel notifikasi BAWAAN Laravel (kolom
     * id uuid, data json, notifiable_type, ...). Tabel notifications di proyek ini mengikuti
     * PRD (user_id, ticket_id, type, subject, ...), jadi relasi bawaan itu akan rusak.
     *
     * Dengan RoutesNotifications saja, $user->notify(...) tetap bisa dipakai (mis. kirim email),
     * dan catatan in-app ditulis ke tabel PRD lewat relasi inAppNotifications() di bawah.
     */
    /** @use HasFactory<UserFactory> */
    use HasFactory, RoutesNotifications;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,           // 'admin' (DB) <-> Role::Admin (PHP)
            'user_type' => UserType::class,  // boleh NULL (Staff/Admin/Super Admin)
            'graduation_year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // ------------------------------------------------------------------
    // Helper peran. Dipakai di middleware, policy, dan view.
    // ------------------------------------------------------------------

    public function isEndUser(): bool
    {
        return $this->role === Role::EndUser;
    }

    public function isStaff(): bool
    {
        return $this->role === Role::Staff;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    /** Apakah user punya salah satu peran yang diberikan. */
    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Alumni hidden link = alumni lulus <= 2021 yang mendaftar sendiri (US-01B).
     * Hanya akun jenis ini (selain Staff/Admin/Super Admin) yang memakai password lokal;
     * akun mahasiswa, dosen, dan alumni >= 2022 login lewat SSO.
     */
    public function isHiddenLinkAlumni(): bool
    {
        return $this->user_type === UserType::Alumni
            && $this->graduation_year !== null
            && $this->graduation_year <= AlumniRegistrationRequest::LAST_GRADUATION_YEAR;
    }

    // ------------------------------------------------------------------
    // Scope query
    // ------------------------------------------------------------------

    /** User::hiddenLinkAlumni() -> syarat yang sama dengan isHiddenLinkAlumni(), tetapi untuk query. */
    #[Scope]
    protected function hiddenLinkAlumni(Builder $query): void
    {
        $query->where('user_type', UserType::Alumni)
            ->whereNotNull('graduation_year')
            ->where('graduation_year', '<=', AlumniRegistrationRequest::LAST_GRADUATION_YEAR);
    }

    // ------------------------------------------------------------------
    // Relasi
    // ------------------------------------------------------------------

    /** Fakultas tempat bertugas (Staff & Admin Fakultas). */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /** Fakultas asal pelapor (dipakai untuk pre-fill Fakultas Tujuan di form tiket). */
    public function originFaculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, 'origin_faculty_id');
    }

    /**
     * Spesialisasi Staff: Kelompok Layanan yang antreannya boleh ia lihat.
     * Many-to-Many lewat tabel pivot staff_specialties (staff_id <-> category_id).
     *
     * Pivot hanya punya created_at (diisi otomatis oleh DB: useCurrent), jadi TIDAK memakai
     * withTimestamps() yang akan mencoba mengisi updated_at juga.
     */
    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'staff_specialties', 'staff_id', 'category_id')
            ->withPivot('created_at');
    }

    /** Baris pivot spesialisasi sebagai model (berguna untuk subquery, lihat Ticket::forStaff). */
    public function staffSpecialties(): HasMany
    {
        return $this->hasMany(StaffSpecialty::class, 'staff_id');
    }

    /** Tiket yang dibuat user ini sebagai pelapor. */
    public function reportedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'reporter_id');
    }

    /** Tiket yang sedang/pernah ditangani user ini sebagai Staff. */
    public function handledTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'handler_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** Semua catatan notifikasi (email & in-app) milik user ini di tabel PRD. */
    public function notificationLogs(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /** Notifikasi lonceng (type = in_app). Nama sengaja beda dari notifications() bawaan Laravel. */
    public function inAppNotifications(): HasMany
    {
        return $this->notificationLogs()->where('type', NotificationType::InApp);
    }

    /** Ping yang DIKIRIM (user = Admin Fakultas). */
    public function sentPings(): HasMany
    {
        return $this->hasMany(PingLog::class, 'admin_id');
    }

    /** Ping yang DITERIMA (user = Staff). */
    public function receivedPings(): HasMany
    {
        return $this->hasMany(PingLog::class, 'staff_id');
    }
}
