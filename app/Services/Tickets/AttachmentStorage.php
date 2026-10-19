<?php

namespace App\Services\Tickets;

use App\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Penyimpanan lampiran pelapor/thread (F-TKU-03, PRD 6.3 "simpan di luar web root").
 *
 * - Disk 'local' = storage/app/private: TIDAK bisa dibuka lewat URL publik. File hanya bisa diunduh lewat
 *   route attachments.show yang mengecek izin (AttachmentPolicy).
 * - Nama file di disk = nama acak (hashName), jadi nama asli dari pengguna tidak pernah dipakai sebagai path
 *   (aman dari path traversal & bentrok nama). Nama asli disimpan di kolom file_name untuk ditampilkan.
 */
class AttachmentStorage
{
    public const DISK = 'local';

    public const MAX_FILES = 3;

    public const MAX_KB = 5120; // 5 MB

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];

    public const MIME_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];

    /**
     * Aturan validasi untuk input file "attachments[]" (dipakai form tiket & balasan thread).
     *   mimetypes  -> isi file dicek (bukan sekadar nama): file .exe yang diganti nama .png tetap ditolak
     *   extensions -> akhiran nama file juga harus jpg/jpeg/png/pdf
     *
     * $field bisa diganti, mis. 'proofs' untuk bukti penyelesaian staff (aturannya sama persis, PRD F-TKS-07).
     *
     * @return array<string, list<string>>
     */
    public static function rules(string $field = 'attachments'): array
    {
        return [
            $field => ['nullable', 'array', 'max:'.self::MAX_FILES],
            "{$field}.*" => [
                'file',
                'max:'.self::MAX_KB,
                'mimetypes:'.implode(',', self::MIME_TYPES),
                'extensions:'.implode(',', self::EXTENSIONS),
            ],
        ];
    }

    /** Pesan validasi ramah untuk rules() di atas. $noun = kata yang dipakai di pesan ("Lampiran", "Bukti"). */
    public static function messages(string $field = 'attachments', string $noun = 'Lampiran'): array
    {
        $lower = mb_strtolower($noun);

        return [
            "{$field}.max" => "{$noun} maksimal ".self::MAX_FILES.' berkas.',
            "{$field}.*.max" => "Ukuran setiap {$lower} maksimal 5 MB.",
            "{$field}.*.mimetypes" => "{$noun} harus berupa gambar JPG/PNG atau dokumen PDF.",
            "{$field}.*.extensions" => "{$noun} harus berakhiran .jpg, .jpeg, .png, atau .pdf.",
            "{$field}.*.uploaded" => "{$noun} gagal diunggah. Pastikan ukurannya tidak lebih dari 5 MB.",
        ];
    }

    /**
     * Simpan file ke disk privat. Belum membuat baris attachments (dipanggil SEBELUM transaksi database,
     * supaya kalau transaksi diulang tidak ada file dobel).
     *
     * $folder: 'attachments' (lampiran pelapor/thread) atau 'proofs' (bukti penyelesaian staff, M3).
     *
     * @return array{file_name: string, file_path: string, mime_type: string, file_size: int}
     */
    public function store(UploadedFile $file, string $folder = 'attachments'): array
    {
        $path = $file->store($folder.'/'.now()->format('Y/m'), self::DISK);

        return [
            'file_name' => $this->cleanName($file->getClientOriginalName()),
            'file_path' => $path,
            'mime_type' => (string) $file->getMimeType(), // dari isi file, bukan dari browser
            'file_size' => (int) $file->getSize(),
        ];
    }

    /**
     * Salin lampiran lama (dipakai "Buat Ulang Tiket"): file fisik ikut disalin supaya tiket baru tidak
     * bergantung pada file tiket lama.
     *
     * @return array{file_name: string, file_path: string, mime_type: string, file_size: int}|null null kalau file lama hilang
     */
    public function copy(Attachment $attachment): ?array
    {
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($attachment->file_path)) {
            return null;
        }

        $extension = pathinfo($attachment->file_path, PATHINFO_EXTENSION);
        $newPath = 'attachments/'.now()->format('Y/m').'/'.Str::random(40).($extension ? ".{$extension}" : '');
        $disk->copy($attachment->file_path, $newPath);

        return [
            'file_name' => $attachment->file_name,
            'file_path' => $newPath,
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->file_size,
        ];
    }

    /** Hapus file yang sudah tersimpan (dipakai kalau transaksi database gagal). */
    public function discard(array $stored): void
    {
        foreach ($stored as $file) {
            Storage::disk(self::DISK)->delete($file['file_path']);
        }
    }

    /** Nama asli dirapikan: tanpa folder, tanpa karakter kontrol, maks. 255 karakter (kolom file_name). */
    private function cleanName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $name)));

        return Str::limit($name !== '' ? $name : 'lampiran', 250, '');
    }
}
