<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use Carbon\CarbonInterface;

/**
 * Nomor tiket TKT-YYYYMM-XXXX (F-TKU-02), contoh TKT-202610-0001.
 *
 * XXXX = nomor urut DALAM BULAN itu, mulai 0001 setiap awal bulan (keputusan teknis; PRD hanya memberi format).
 * Setelah 9999 tiket dalam sebulan, nomor lanjut menjadi 5 digit (TKT-202610-10000) tanpa error.
 *
 * Aman dari race condition (dua pelapor menekan "Kirim" bersamaan):
 *   1. next() WAJIB dipanggil di dalam DB::transaction().
 *   2. lockForUpdate() mengunci baris nomor terakhir bulan itu (MySQL InnoDB: SELECT ... FOR UPDATE),
 *      sehingga transaksi kedua MENUNGGU sampai transaksi pertama selesai, lalu membaca nomor yang baru.
 *   3. Lapisan terakhir: kolom ticket_number UNIQUE. Kalau tetap bentrok (mis. bulan baru, belum ada baris
 *      untuk dikunci), insert gagal dengan UniqueConstraintViolationException dan TicketService mencoba lagi.
 */
class TicketNumberGenerator
{
    public function next(?CarbonInterface $at = null): string
    {
        $prefix = 'TKT-'.($at ?? now())->format('Ym').'-';

        $last = Ticket::query()
            ->where('ticket_number', 'like', $prefix.'%')
            // Urut panjang dulu supaya "10000" dianggap lebih besar dari "9999" (urutan teks biasa terbalik).
            ->orderByRaw('LENGTH(ticket_number) DESC')
            ->orderByDesc('ticket_number')
            ->lockForUpdate()
            ->value('ticket_number');

        $sequence = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
