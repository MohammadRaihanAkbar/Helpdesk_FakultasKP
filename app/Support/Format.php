<?php

namespace App\Support;

/**
 * Format angka untuk tampilan (Bahasa Indonesia).
 *
 * Kenapa tidak memakai Illuminate\Support\Number::fileSize()? Fungsi itu butuh ekstensi PHP "intl",
 * yang tidak selalu aktif di setiap komputer. Versi kecil ini tidak butuh ekstensi apa pun.
 */
class Format
{
    /** 245760 -> "240 KB", 5242880 -> "5 MB", 1536000 -> "1,5 MB" */
    public static function fileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024).' KB';
        }

        $mb = $bytes / 1024 / 1024;

        return rtrim(rtrim(number_format($mb, 1, ',', '.'), '0'), ',').' MB';
    }
}
