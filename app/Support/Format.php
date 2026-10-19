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

    /**
     * Lama waktu antara dua titik, dibulatkan ke bawah, maks. 2 satuan:
     * "2 hari 3 jam", "5 jam 10 menit", "12 menit", "kurang dari 1 menit".
     * Urutan $from/$to tidak penting (selalu dihitung selisih positif).
     */
    public static function duration(\DateTimeInterface $from, \DateTimeInterface $to): string
    {
        $minutes = intdiv(abs($to->getTimestamp() - $from->getTimestamp()), 60);

        if ($minutes < 1) {
            return 'kurang dari 1 menit';
        }

        $parts = array_filter([
            'hari' => intdiv($minutes, 1440),
            'jam' => intdiv($minutes % 1440, 60),
            'menit' => $minutes % 60,
        ]);

        return collect($parts)->take(2)->map(fn (int $value, string $unit) => "{$value} {$unit}")->implode(' ');
    }

    /**
     * Lama waktu dari jumlah detik (rata-rata Time-to-Claim / Resolution Time). null -> "–".
     * Contoh: 5400 -> "1 jam 30 menit", 93600 -> "1 hari 2 jam".
     */
    public static function seconds(int|float|null $seconds): string
    {
        if ($seconds === null) {
            return '–';
        }

        $base = new \DateTimeImmutable('@0');

        return self::duration($base, $base->modify('+'.(int) round($seconds).' seconds'));
    }

    /** Persentase gaya Indonesia: 87.5 -> "87,5%", 90.0 -> "90%", null -> "–". */
    public static function percent(?float $value): string
    {
        if ($value === null) {
            return '–';
        }

        return number_format($value, $value == floor($value) ? 0 : 1, ',', '.').'%';
    }
}
