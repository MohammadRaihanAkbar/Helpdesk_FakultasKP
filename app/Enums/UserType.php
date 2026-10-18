<?php

namespace App\Enums;

/**
 * Jenis pelapor (kolom users.user_type, category_audiences.user_type, tickets.reporter_type).
 *
 * Nilai di database tetap Bahasa Indonesia (mengikuti migration), nama case-nya Inggris
 * supaya konsisten dengan konvensi kode (rencana teknis bab 2).
 *
 * Jenis pelapor menentukan Kelompok Layanan mana yang tampil di form tiket
 * (lihat scope Category::visibleTo()).
 */
enum UserType: string
{
    case Mahasiswa = 'mahasiswa';
    case Dosen = 'dosen';
    case Alumni = 'alumni';

    public function label(): string
    {
        return match ($this) {
            self::Mahasiswa => 'Mahasiswa',
            self::Dosen => 'Dosen',
            self::Alumni => 'Alumni',
        };
    }
}
