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
use Illuminate\Support\Carbon;
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

    /** Batas pesan PUBLIK per tiket (gabungan pelapor + staff). Catatan internal tidak dihitung (PRD 8.2 tabel 7). */
    public const THREAD_LIMIT = 20;

    /**
     * Alasan penolakan BAKU (F-TKS-05). Tiket yang ditolak dengan alasan ini boleh "Buat Ulang Tiket" (US-05):
     * /tickets/new?from={id} membuka form baru dengan data tiket lama.
     * Staff (M3) menyimpan alasan ini apa adanya, boleh diikuti keterangan: "Salah fakultas/kategori: seharusnya ke FEBI".
     */
    public const REJECTION_WRONG_TARGET = 'Salah fakultas/kategori';

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

    /** Pesan publik saja (yang dihitung dalam batas 20 pesan dan terlihat oleh pelapor). */
    public function publicComments(): HasMany
    {
        return $this->comments()->where('is_internal_note', false);
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

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    /** Kelompok Layanan tiket (induk kategori leaf, atau kategori itu sendiri). */
    public function group(): Category
    {
        return $this->category->group();
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /** Ditolak dengan alasan baku "Salah fakultas/kategori" -> boleh dibuat ulang (US-05, F-TKU-09). */
    public function isRejectedForWrongTarget(): bool
    {
        return $this->status === TicketStatus::Ditolak
            && str_starts_with((string) $this->rejection_reason, self::REJECTION_WRONG_TARGET);
    }

    /**
     * Linimasa riwayat aktivitas (US-04, PRD 8.2 tabel 6):
     *   Dibuat -> Diambil Staff (+ nama) -> Respons pertama -> Selesai/Ditolak.
     * Langkah yang belum terjadi tetap dikembalikan dengan 'at' = null supaya pelapor tahu tahap berikutnya.
     *
     * @return list<array{label: string, at: ?Carbon, done: bool}>
     */
    public function timeline(): array
    {
        $closedLabel = match ($this->status) {
            TicketStatus::Selesai => 'Selesai',
            TicketStatus::Ditolak => 'Ditolak',
            default => 'Selesai / Ditolak',
        };

        return [
            ['label' => 'Tiket dibuat', 'at' => $this->created_at, 'done' => true],
            ['label' => $this->handler ? 'Diambil oleh '.$this->handler->name : 'Diambil Staff', 'at' => $this->assigned_at, 'done' => $this->assigned_at !== null],
            ['label' => 'Respons pertama Staff', 'at' => $this->first_responded_at, 'done' => $this->first_responded_at !== null],
            ['label' => $closedLabel, 'at' => $this->resolved_at, 'done' => $this->resolved_at !== null],
        ];
    }
}
