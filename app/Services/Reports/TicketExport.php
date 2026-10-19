<?php

namespace App\Services\Reports;

use App\Models\ResolutionProof;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Ekspor daftar tiket ke CSV / Excel (US-15, F-ADM-08, PRD 9.2 GET /reports/export).
 *
 * Kolom PERSIS sesuai PRD US-15 (urutan juga sama):
 *   No Tiket, Pelapor, Jenis Pelapor, Kelompok Layanan, Jenis Masalah, Status, Tanggal Dibuat, Tanggal Diambil,
 *   Target SLA, Tanggal Selesai/Ditolak, Rating, Handler (Staff), Bukti Penyelesaian
 *
 * Excel (.xlsx) dibuat dengan library openspout/openspout: ringan, menulis baris demi baris (hemat memori),
 * dan TIDAK butuh ekstensi PHP intl. Ia butuh ekstensi zip + xmlwriter (bawaan Laragon).
 *
 * Pelapor kategori sensitif "Integritas Dosen" tetap ditulis namanya, karena Admin Fakultas memang
 * berhak melihat identitasnya (PRD 2.7 aturan 10). File ekspor hanya berisi tiket fakultas admin.
 */
class TicketExport
{
    public const HEADERS = [
        'No Tiket', 'Pelapor', 'Jenis Pelapor', 'Kelompok Layanan', 'Jenis Masalah', 'Status', 'Tanggal Dibuat',
        'Tanggal Diambil', 'Target SLA', 'Tanggal Selesai/Ditolak', 'Rating', 'Handler (Staff)', 'Bukti Penyelesaian',
    ];

    /** Format tanggal di file: mudah diurutkan dan dibaca Excel. */
    public const DATE_FORMAT = 'Y-m-d H:i';

    /**
     * Baris data (tanpa judul kolom), dibaca per 200 tiket supaya hemat memori.
     *
     * @return \Generator<int, list<string|int>>
     */
    public function rows(Builder $query): \Generator
    {
        $tickets = (clone $query)
            ->with(['reporter', 'category.parent', 'handler', 'rating', 'resolutionProofs'])
            ->orderByDesc('tickets.created_at')
            ->orderByDesc('tickets.id')
            ->lazy(200);

        foreach ($tickets as $ticket) {
            yield $this->row($ticket);
        }
    }

    /** @return list<string|int> */
    public function row(Ticket $ticket): array
    {
        $category = $ticket->category;

        return [
            $ticket->ticket_number,
            $ticket->reporter?->name ?? '',
            $ticket->reporter_type->label(),
            $category->isGroup() ? $category->name : $category->parent->name,
            $category->isGroup() ? '-' : $category->name,          // kelompok tanpa Jenis Masalah
            $ticket->status->label(),
            $ticket->created_at->format(self::DATE_FORMAT),
            $ticket->assigned_at?->format(self::DATE_FORMAT) ?? '',
            $ticket->sla_target_hours.' jam',
            $ticket->resolved_at?->format(self::DATE_FORMAT) ?? '',
            $ticket->rating?->score ?? '',
            $ticket->handler?->name ?? '',
            $ticket->resolutionProofs->map(fn (ResolutionProof $proof) => $proof->file_name)->implode('; '),
        ];
    }

    /**
     * Tulis CSV ke output (dipakai response()->streamDownload).
     * - Diawali BOM UTF-8 supaya huruf seperti "—" dan "›" terbaca benar saat dibuka di Excel.
     * - Pemisah koma (standar RFC 4180). Bila Excel berbahasa Indonesia menampilkannya dalam satu kolom,
     *   pakai tombol Excel (.xlsx) atau menu Data → From Text/CSV.
     * - Teks yang diawali = + - @ diberi tanda petik satu di depan, supaya tidak dijalankan sebagai rumus
     *   oleh Excel (pencegahan "CSV injection" dari judul/nama berkas yang diketik pengguna).
     */
    public function writeCsv(Builder $query, $handle): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::HEADERS, ',', '"', '');

        foreach ($this->rows($query) as $row) {
            fputcsv($handle, array_map([self::class, 'safeCell'], $row), ',', '"', '');
        }
    }

    /** Tulis file .xlsx ke $path (lalu controller mengirimnya sebagai unduhan). */
    public function writeXlsx(Builder $query, string $path, string $sheetName = 'Tiket'): void
    {
        $options = new Options;
        $options->setColumnWidth(18, 1);          // No Tiket
        $options->setColumnWidth(26, 2, 4, 5, 12); // Pelapor, Kelompok, Jenis, Handler
        $options->setColumnWidth(18, 7, 8, 10);   // tanggal
        $options->setColumnWidth(30, 13);         // bukti

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(mb_substr($sheetName, 0, 31)); // batas nama sheet Excel

        $writer->addRow(Row::fromValues(self::HEADERS, (new Style)->setFontBold()));
        foreach ($this->rows($query) as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
    }

    public static function safeCell(string|int $value): string|int
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) && $value !== '-'
            ? "'".$value
            : $value;
    }
}
