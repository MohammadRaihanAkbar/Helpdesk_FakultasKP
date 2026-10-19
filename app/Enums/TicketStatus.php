<?php

namespace App\Enums;

/**
 * Status tiket (kolom tickets.status). Hanya 4 status, tanpa 'kedaluwarsa' (PRD v6.0).
 *
 * Alur yang sah (PRD 4.5): menunggu -> diproses -> selesai | ditolak.
 * Daftar transisi yang sah ada di canTransitionTo(); yang menjaganya saat tiket diubah adalah
 * App\Services\Tickets\TicketWorkflowService (M3). Enum ini juga menyimpan label, warna badge,
 * dan apakah tiket masih terbuka.
 */
enum TicketStatus: string
{
    case Menunggu = 'menunggu';
    case Diproses = 'diproses';
    case Selesai = 'selesai';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu',
            self::Diproses => 'Diproses',
            self::Selesai => 'Selesai',
            self::Ditolak => 'Ditolak',
        };
    }

    /**
     * "Kunci" warna badge, BUKAN kode warna langsung.
     *
     * Kenapa kunci? Tema UI belum dipilih. Nanti komponen <x-ui.badge> yang memetakan
     * kunci ini ke kelas CSS/warna tema, jadi ganti tema tidak perlu menyentuh enum.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Menunggu => 'warning',
            self::Diproses => 'info',
            self::Selesai => 'success',
            self::Ditolak => 'danger',
        };
    }

    /** Tiket masih terbuka = belum ditutup (menunggu atau diproses). */
    public function isOpen(): bool
    {
        return in_array($this, self::openStatuses(), true);
    }

    /**
     * Boleh pindah dari status ini ke status $to? (diagram PRD 4.5)
     *
     *   Menunggu -> Diproses            (Staff mengambil tiket)
     *   Diproses -> Selesai | Ditolak   (Staff menutup tiket)
     *
     * Selain itu DITOLAK: tidak bisa mundur (Diproses -> Menunggu), tidak bisa melompat
     * (Menunggu -> Selesai), dan tiket yang sudah ditutup tidak bisa dibuka lagi.
     */
    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Menunggu => $to === self::Diproses,
            self::Diproses => in_array($to, self::closedStatuses(), true),
            self::Selesai, self::Ditolak => false,
        };
    }

    /** @return list<self> */
    public static function openStatuses(): array
    {
        return [self::Menunggu, self::Diproses];
    }

    /** @return list<self> status "ditutup": keduanya mengisi resolved_at */
    public static function closedStatuses(): array
    {
        return [self::Selesai, self::Ditolak];
    }
}
