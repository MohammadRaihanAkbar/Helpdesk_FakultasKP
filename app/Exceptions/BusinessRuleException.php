<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Pelanggaran ATURAN BISNIS PRD (bukan sekadar salah format input).
 *
 * Contoh: menghapus kategori standar, memilih kategori yang bukan leaf, mengirim pesan ke-21.
 * Setiap pelanggaran punya kode baku dari PRD bab 9 (STANDARD_CATEGORY_LOCKED, INVALID_CATEGORY,
 * THREAD_LIMIT_REACHED, ...) supaya mudah dicari di kode, di tes, dan nanti di API.
 *
 * Cara pakai di service:
 *   throw BusinessRuleException::standardCategoryLocked();
 *
 * Laravel memanggil render() otomatis:
 *   - request JSON (Postman / fetch)  -> {"success": false, "error": {"code": ..., "message": ...}} + status HTTP
 *   - request form biasa (browser)    -> kembali ke halaman sebelumnya, pesan tampil sebagai error validasi
 *                                        pada field $field (atau kotak merah di atas halaman bila tanpa field)
 */
class BusinessRuleException extends RuntimeException
{
    /** Nama "field" untuk error yang tidak menempel ke input tertentu (ditampilkan layout sebagai alert). */
    public const GENERAL_FIELD = 'rule';

    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly ?string $field = null,
    ) {
        parent::__construct($message);
    }

    // ------------------------------------------------------------------
    // Daftar pelanggaran (pesan sesuai PRD bila PRD menuliskannya)
    // ------------------------------------------------------------------

    public static function standardCategoryLocked(): self
    {
        return new self(
            'STANDARD_CATEGORY_LOCKED',
            'Kategori standar tidak dapat dihapus. Nonaktifkan kategori jika tidak digunakan di fakultas Anda.',
            409,
        );
    }

    public static function standardCategoryFieldLocked(): self
    {
        return new self(
            'STANDARD_CATEGORY_LOCKED',
            'Nama, induk, dan audiens kategori standar tidak dapat diubah. Anda hanya dapat mengubah Target SLA, deskripsi, dan status aktif.',
            422,
        );
    }

    public static function categoryInUse(): self
    {
        return new self(
            'CATEGORY_IN_USE',
            'Kategori ini sudah pernah dipakai tiket sehingga tidak dapat dihapus. Nonaktifkan saja agar tidak muncul di form tiket.',
            409,
        );
    }

    public static function invalidParent(string $message): self
    {
        return new self('INVALID_PARENT', $message, 422, 'parent_id');
    }

    public static function invalidCategory(): self
    {
        return new self(
            'INVALID_CATEGORY',
            'Kategori tidak valid: pilih Kelompok Layanan / Jenis Masalah yang aktif dan tersedia untuk Anda di fakultas tujuan.',
            422,
            'category_id',
        );
    }

    public static function invalidSpecialty(): self
    {
        return new self(
            'INVALID_SPECIALTY',
            'Spesialisasi hanya boleh berisi Kelompok Layanan aktif milik fakultas Anda.',
            422,
            'category_ids',
        );
    }

    public static function threadLimitReached(int $limit): self
    {
        return new self(
            'THREAD_LIMIT_REACHED',
            "Batas diskusi tercapai ({$limit}/{$limit} pesan). Hubungi staf secara langsung jika diperlukan.",
            409,
            'body',
        );
    }

    public static function threadClosed(): self
    {
        return new self('THREAD_CLOSED', 'Tiket sudah ditutup, thread diskusi tidak dapat dibalas lagi.', 409, 'body');
    }

    public static function tooManyAttachments(int $max): self
    {
        return new self('TOO_MANY_ATTACHMENTS', "Lampiran maksimal {$max} berkas.", 422, 'attachments');
    }

    // ------------------------------------------------------------------
    // Tampilan error
    // ------------------------------------------------------------------

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => $this->errorCode, 'message' => $this->getMessage()],
            ], $this->status);
        }

        return back()
            // input() = isian teks saja (berkas upload tidak bisa disimpan di session).
            ->withInput(Arr::except($request->input(), ['password', 'password_confirmation']))
            ->withErrors([$this->field ?? self::GENERAL_FIELD => $this->getMessage()]);
    }
}
