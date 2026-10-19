<?php

namespace App\Services\Reports;

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Filter halaman Laporan & Ekspor Admin Fakultas (US-15).
 *
 * Satu objek ini dipakai oleh tabel laporan, kartu KPI, grafik, DAN file ekspor CSV/XLSX, sehingga
 * angka ringkasan selalu bisa dicocokkan dengan isi file yang diunduh.
 *
 * Periode (PRD US-15: 7 hari terakhir, 30 hari terakhir, Custom range) menyaring tickets.created_at
 * = "tiket yang MASUK pada periode itu". Semua KPI dihitung dari kelompok tiket tersebut.
 *
 * Query string yang dibaca:
 *   period    7 | 30 | custom            (bawaan 30)
 *   from, to  Y-m-d (hanya untuk custom; tanggal terbalik ditukar otomatis)
 *   category  id Kelompok Layanan ATAU id Jenis Masalah milik fakultas admin
 *   staff     id Staff penangan (handler) di fakultas admin
 *   status    menunggu | diproses | selesai | ditolak
 */
final class ReportFilters
{
    public const PERIODS = ['7' => '7 hari terakhir', '30' => '30 hari terakhir', 'custom' => 'Rentang tanggal sendiri'];

    /** Rentang custom maksimal 1 tahun supaya grafik & ekspor tetap ringan. */
    public const MAX_DAYS = 366;

    public function __construct(
        public readonly int $facultyId,
        public readonly string $period,
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly ?Category $category = null,
        public readonly ?User $staff = null,
        public readonly ?TicketStatus $status = null,
    ) {}

    public static function fromRequest(Request $request, int $facultyId): self
    {
        $period = array_key_exists((string) $request->query('period'), self::PERIODS) ? (string) $request->query('period') : '30';
        $today = now()->endOfDay();

        if ($period === 'custom') {
            $from = self::date($request->query('from'));
            $to = self::date($request->query('to'));

            if (! $from && ! $to) {
                $period = '30';
            } else {
                $from ??= $to->copy()->subDays(29);
                $to ??= now();
                if ($from->greaterThan($to)) {
                    [$from, $to] = [$to, $from];
                }
                $from = $from->copy()->startOfDay();
                $to = $to->copy()->endOfDay();
                if ($from->diffInDays($to) > self::MAX_DAYS) {
                    $from = $to->copy()->subDays(self::MAX_DAYS - 1)->startOfDay();
                }
            }
        }

        if ($period !== 'custom') {
            $to = $today;
            $from = $today->copy()->subDays((int) $period - 1)->startOfDay(); // 7 hari = hari ini + 6 hari sebelumnya
        }

        // Kategori & staff harus milik fakultas admin; id fakultas lain diabaikan (bukan error).
        $category = $request->filled('category')
            ? Category::forFaculty($facultyId)->find($request->integer('category'))
            : null;
        $staff = $request->filled('staff')
            ? User::where('role', 'staff')->where('faculty_id', $facultyId)->find($request->integer('staff'))
            : null;

        return new self(
            $facultyId,
            $period,
            $from,
            $to,
            $category,
            $staff,
            TicketStatus::tryFrom((string) $request->query('status')),
        );
    }

    /**
     * Query tiket fakultas sesuai filter. Nama kolom ditulis lengkap (tickets.xxx) supaya aman
     * ketika query ini digabung (join) dengan tabel lain yang juga punya created_at/status.
     */
    public function query(): Builder
    {
        return Ticket::query()
            ->where('tickets.faculty_id', $this->facultyId)
            ->whereBetween('tickets.created_at', [$this->from, $this->to])
            ->when($this->category, fn (Builder $query, Category $category) => $category->parent_id === null
                ? $query->inGroup($category->id)                 // Kelompok -> semua Jenis Masalah di bawahnya
                : $query->where('tickets.category_id', $category->id))
            ->when($this->staff, fn (Builder $query, User $staff) => $query->where('tickets.handler_id', $staff->id))
            ->when($this->status, fn (Builder $query, TicketStatus $status) => $query->where('tickets.status', $status));
    }

    /** Ada filter selain periode? (untuk tombol Reset & teks "sesuai filter") */
    public function narrowed(): bool
    {
        return $this->category || $this->staff || $this->status;
    }

    /** Isi query string yang sama, untuk tautan ekspor dan paginasi. */
    public function toQuery(): array
    {
        return array_filter([
            'period' => $this->period,
            'from' => $this->period === 'custom' ? $this->from->format('Y-m-d') : null,
            'to' => $this->period === 'custom' ? $this->to->format('Y-m-d') : null,
            'category' => $this->category?->id,
            'staff' => $this->staff?->id,
            'status' => $this->status?->value,
        ]);
    }

    /** "1 Okt 2026 – 30 Okt 2026" */
    public function label(): string
    {
        return $this->from->translatedFormat('j M Y').' – '.$this->to->translatedFormat('j M Y');
    }

    /** Satuan grafik volume: ≤ 31 hari per hari, ≤ 120 hari per minggu, selebihnya per bulan. */
    public function unit(?string $requested = null): string
    {
        if (in_array($requested, ['day', 'week', 'month'], true)) {
            return $requested;
        }

        $days = $this->from->diffInDays($this->to) + 1;

        return match (true) {
            $days <= 31 => 'day',
            $days <= 120 => 'week',
            default => 'month',
        };
    }

    private static function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }
    }
}
