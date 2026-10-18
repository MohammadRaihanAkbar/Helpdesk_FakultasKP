<?php

namespace App\Models;

use App\Enums\Role;
use App\Services\CategoryCatalogService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabel 1: faculties — data master fakultas UIN SSC.
 */
#[Fillable(['code', 'name', 'slug', 'description', 'is_active'])]
class Faculty extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Event model: dijalankan otomatis oleh Eloquent.
     *
     * Setiap kali fakultas BARU dibuat lewat model (mis. Super Admin menambah fakultas di M5),
     * Katalog Kategori Layanan Standar langsung disalin ke fakultas itu (F-SYS-01).
     * Jadi tidak ada fakultas yang "kosong" tanpa kategori.
     *
     * Catatan: FacultySeeder memakai DB::table() (query builder), yang TIDAK memicu event model.
     * Karena itu CategoryCatalogSeeder tetap dipanggil di DatabaseSeeder untuk 6 fakultas awal.
     */
    protected static function booted(): void
    {
        static::created(function (Faculty $faculty) {
            app(CategoryCatalogService::class)->seedForFaculty($faculty);
        });
    }

    /** Semua kategori (kelompok + jenis masalah) milik fakultas ini. */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** Tiket yang DITUJUKAN ke fakultas ini. */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** Pengguna yang BERTUGAS di fakultas ini (Staff & Admin Fakultas) -> users.faculty_id. */
    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'faculty_id');
    }

    public function staff(): HasMany
    {
        return $this->members()->where('role', Role::Staff);
    }

    public function admins(): HasMany
    {
        return $this->members()->where('role', Role::Admin);
    }

    /** Pelapor yang BERASAL dari fakultas ini -> users.origin_faculty_id. */
    public function originUsers(): HasMany
    {
        return $this->hasMany(User::class, 'origin_faculty_id');
    }
}
