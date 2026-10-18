<?php

namespace App\Models;

use App\Enums\TicketStatus;
use App\Enums\UserType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * Tabel 6: tickets — data aduan utama.
 *
 * Dua relasi ke tabel users yang sama dengan peran berbeda:
 *   reporter() -> pelapor (reporter_id)
 *   handler()  -> Staff penangan (handler_id, NULL selama masih "menunggu")
 */
#[Fillable([
    'ticket_number', 'faculty_id', 'title', 'description',
    'status', 'rejection_reason',
    'reporter_id', 'reporter_type', 'handler_id',
    'category_id', 'sla_target_hours',
    'assigned_at', 'first_responded_at', 'resolved_at',
])]
class Ticket extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'reporter_type' => UserType::class,
            'sla_target_hours' => 'integer',
            'assigned_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------------
    // Relasi
    // ------------------------------------------------------------------

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }

    /** Kategori LEAF yang dipilih pelapor. Kelompoknya: $ticket->category->group(). */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Fakultas TUJUAN tiket. */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** Lampiran form awal tiket (attachments.ticket_id). Lampiran pesan thread ada di Comment. */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function resolutionProofs(): HasMany
    {
        return $this->hasMany(ResolutionProof::class);
    }

    /** 1 tiket maksimal 1 rating (dijamin unique ticket_id di migration). */
    public function rating(): HasOne
    {
        return $this->hasOne(Rating::class);
    }

    public function pingLogs(): HasMany
    {
        return $this->hasMany(PingLog::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    // ------------------------------------------------------------------
    // Scope
    // ------------------------------------------------------------------

    /** Tiket yang masih terbuka: menunggu atau diproses. */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', TicketStatus::openStatuses());
    }

    /** Tiket yang sudah ditutup: selesai atau ditolak. */
    #[Scope]
    protected function closed(Builder $query): void
    {
        $query->whereIn('status', TicketStatus::closedStatuses());
    }

    /**
     * Tiket yang BOLEH dilihat seorang Staff (dasar halaman Antrean, PRD 4.6):
     *   1. fakultas tujuan tiket = fakultas tempat Staff bertugas, dan
     *   2. KELOMPOK dari kategori tiket termasuk spesialisasi Staff.
     *
     * Kenapa COALESCE(categories.parent_id, categories.id)?
     *   Tiket menyimpan kategori LEAF. Kalau leaf-nya Jenis Masalah, kelompoknya = parent_id.
     *   Kalau leaf-nya Kelompok tanpa anak, kelompoknya = id-nya sendiri.
     *   COALESCE mengambil nilai pertama yang tidak NULL, jadi hasilnya selalu id kelompok.
     *
     * Scope ini sengaja tidak memfilter status, supaya bisa dirangkai:
     *   Ticket::forStaff($staff)->where('status', TicketStatus::Menunggu)->get();
     */
    #[Scope]
    protected function forStaff(Builder $query, User $staff): void
    {
        if ($staff->faculty_id === null) {
            // Staff tanpa fakultas tidak boleh melihat apa pun.
            // (where('faculty_id', null) justru akan diubah Laravel menjadi WHERE faculty_id IS NULL.)
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('tickets.faculty_id', $staff->faculty_id)
            ->whereHas('category', function (Builder $category) use ($staff) {
                $category->whereIn(
                    DB::raw('COALESCE(categories.parent_id, categories.id)'),
                    StaffSpecialty::query()->select('category_id')->where('staff_id', $staff->id)
                );
            });
    }
}
