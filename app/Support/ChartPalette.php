<?php

namespace App\Support;

/**
 * Warna grafik dari token tema (resources/css/app.css), bukan kode warna acak.
 * Nama kelas ditulis utuh supaya ditemukan Tailwind saat `npm run build`.
 *
 *   stroke -> potongan donat (SVG),  bg -> batang & kotak legenda (HTML)
 */
class ChartPalette
{
    public const COLORS = [
        ['stroke' => 'stroke-primary', 'bg' => 'bg-primary'],
        ['stroke' => 'stroke-accent', 'bg' => 'bg-accent'],
        ['stroke' => 'stroke-diproses-dot', 'bg' => 'bg-diproses-dot'],
        ['stroke' => 'stroke-ditolak-dot', 'bg' => 'bg-ditolak-dot'],
        ['stroke' => 'stroke-selesai-dot', 'bg' => 'bg-selesai-dot'],
        ['stroke' => 'stroke-menunggu-dot', 'bg' => 'bg-menunggu-dot'],
        ['stroke' => 'stroke-primary-dark', 'bg' => 'bg-primary-dark'],
        ['stroke' => 'stroke-accent-ink', 'bg' => 'bg-accent-ink'],
        ['stroke' => 'stroke-diproses-fg', 'bg' => 'bg-diproses-fg'],
        ['stroke' => 'stroke-line-strong', 'bg' => 'bg-line-strong'],
    ];

    /** @return array{stroke: string, bg: string} */
    public static function at(int $index): array
    {
        return self::COLORS[$index % count(self::COLORS)];
    }
}
