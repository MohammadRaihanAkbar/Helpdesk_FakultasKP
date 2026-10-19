<?php

namespace App\Models;

use App\Enums\UserType;
use App\Services\CategoryCatalogService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabel 3: categories — katalog layanan per fakultas, hierarki 2 tingkat.
 *
 *   parent_id = NULL   -> Kelompok Layanan (group)
 *   parent_id = terisi -> Jenis Masalah (anak)
 *
 * Istilah "leaf" = kategori yang BOLEH dipilih di tiket:
 *   Jenis Masalah, atau Kelompok Layanan yang tidak punya Jenis Masalah (PRD 2.7 aturan 5).
 */
#[Fillable(['faculty_id', 'parent_id', 'name', 'description', 'sla_hours', 'is_standard', 'is_active'])]
class Category extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sla_hours' => 'integer',
            'is_standard' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // ------------------------------------------------------------------
    // Relasi
    // ------------------------------------------------------------------

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /** Kelompok induk (NULL kalau kategori ini sendiri adalah Kelompok Layanan). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** Jenis Masalah di bawah kelompok ini. */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /** Baris audiens (hanya ada di Kelompok Layanan; anak mewarisi dari induk). */
    public function audiences(): HasMany
    {
        return $this->hasMany(CategoryAudience::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** Staff yang dipetakan ke kelompok ini (kebalikan dari User::specialties()). */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'staff_specialties', 'category_id', 'staff_id')
            ->withPivot('created_at');
    }

    // ------------------------------------------------------------------
    // Scope: potongan query yang bisa dirangkai, mis.
    //   Category::topLevel()->active()->visibleTo(UserType::Dosen)->get();
    // Atribut #[Scope] membuat method protected ini bisa dipanggil sebagai Category::topLevel().
    // ------------------------------------------------------------------

    /** Hanya Kelompok Layanan (tingkat atas). */
    #[Scope]
    protected function topLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /** Hanya kategori yang statusnya On. (Status induk dicek terpisah lewat isSelectable().) */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Kategori milik satu fakultas. */
    #[Scope]
    protected function forFaculty(Builder $query, int $facultyId): void
    {
        $query->where('faculty_id', $facultyId);
    }

    /** Hanya kategori leaf: tidak punya anak sama sekali. */
    #[Scope]
    protected function leaves(Builder $query): void
    {
        $query->whereDoesntHave('children');
    }

    /**
     * Kategori yang boleh DILIHAT oleh jenis pelapor tertentu.
     *
     * Audiens hanya disimpan di Kelompok Layanan, jadi:
     *   - Kelompok  -> cek audiens dirinya sendiri
     *   - Jenis Masalah -> cek audiens INDUKNYA (pewarisan, PRD 2.7 aturan 6)
     */
    #[Scope]
    protected function visibleTo(Builder $query, UserType $userType): void
    {
        $query->where(function (Builder $q) use ($userType) {
            $q->whereHas('audiences', fn (Builder $a) => $a->where('user_type', $userType))
                ->orWhereHas('parent.audiences', fn (Builder $a) => $a->where('user_type', $userType));
        });
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    public function isGroup(): bool
    {
        return $this->parent_id === null;
    }

    /** Kelompok Layanan dari kategori ini: induknya, atau dirinya sendiri kalau ia kelompok. */
    public function group(): Category
    {
        return $this->isGroup() ? $this : $this->parent;
    }

    /**
     * Leaf = tidak punya Jenis Masalah. Jenis Masalah selalu leaf (maksimal 2 tingkat).
     * Kalau relasi children sudah di-load (eager loading), pakai itu supaya tidak query ulang.
     */
    public function isLeaf(): bool
    {
        if (! $this->isGroup()) {
            return true;
        }

        return $this->relationLoaded('children')
            ? $this->children->isEmpty()
            : ! $this->children()->exists();
    }

    /** Boleh dipilih di form tiket: leaf, On, dan induknya (jika ada) juga On (PRD 2.7 aturan 8). */
    public function isSelectable(): bool
    {
        return $this->isLeaf()
            && $this->is_active
            && ($this->isGroup() || $this->parent->is_active);
    }

    /** Apakah jenis pelapor ini termasuk audiens kategori (lewat kelompoknya). */
    public function isVisibleTo(UserType $userType): bool
    {
        return $this->group()->audiences()->where('user_type', $userType)->exists();
    }

    /**
     * Aktif "sungguhan": dirinya On DAN induknya (kalau ada) juga On.
     * Status Off menurun ke anak (PRD 2.7 aturan 8): Jenis Masalah di bawah kelompok yang Off ikut tidak tampil,
     * walaupun kolom is_active milik anak itu sendiri masih TRUE.
     */
    public function isEffectivelyActive(): bool
    {
        return $this->is_active && ($this->isGroup() || $this->parent->is_active);
    }

    /** Kelompok sensitif (Integritas Dosen) atau Jenis Masalah di bawahnya. */
    public function isSensitive(): bool
    {
        $group = $this->group();

        return $group->is_standard && in_array($group->name, CategoryCatalogService::SENSITIVE_GROUPS, true);
    }

    /**
     * Daftar jenis pelapor (audiens) kelompok ini; Jenis Masalah mewarisi dari induk.
     *
     * @return list<UserType>
     */
    public function audienceTypes(): array
    {
        return $this->group()->audiences
            ->pluck('user_type')
            ->sortBy(fn (UserType $type) => array_search($type, UserType::cases(), true))
            ->values()
            ->all();
    }

    /** Label kategori untuk daftar tiket: "Kelompok › Jenis", atau nama kelompok saja. */
    public function pathLabel(): string
    {
        return $this->isGroup() ? $this->name : $this->parent->name.' › '.$this->name;
    }

    /**
     * Pernah dipakai tiket? Untuk kelompok, tiket di Jenis Masalah-nya juga dihitung,
     * karena menghapus kelompok berarti ikut menghapus anak-anaknya.
     */
    public function isUsedByTickets(): bool
    {
        return Ticket::query()
            ->where('category_id', $this->id)
            ->orWhereIn('category_id', $this->children()->select('id'))
            ->exists();
    }
}
