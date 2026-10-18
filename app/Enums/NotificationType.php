<?php

namespace App\Enums;

/**
 * Jenis notifikasi di tabel notifications versi PRD (kolom type).
 *   email  = catatan bahwa email sudah dikirim
 *   in_app = notifikasi lonceng di dalam aplikasi
 */
enum NotificationType: string
{
    case Email = 'email';
    case InApp = 'in_app';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::InApp => 'Notifikasi Aplikasi',
        };
    }
}
