<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\ResolutionProof;
use App\Services\Tickets\AttachmentStorage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduh berkas privat (lampiran & bukti penyelesaian), dipakai semua peran.
 *
 *   GET /attachments/{attachment}  -> AttachmentPolicy::download
 *   GET /proofs/{proof}            -> ResolutionProofPolicy::download
 *
 * Berkas disimpan di storage/app/private (disk 'local'), jadi TIDAK punya URL publik.
 * Satu-satunya jalan mengambilnya adalah lewat sini, setelah izin dicek.
 */
class FileController extends Controller
{
    public function attachment(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('download', $attachment);

        return $this->download($attachment->file_path, $attachment->file_name, $attachment->mime_type);
    }

    public function proof(ResolutionProof $proof): StreamedResponse
    {
        Gate::authorize('download', $proof);

        return $this->download($proof->file_path, $proof->file_name, $proof->mime_type);
    }

    private function download(string $path, string $name, string $mime): StreamedResponse
    {
        $disk = Storage::disk(AttachmentStorage::DISK);
        abort_unless($disk->exists($path), 404, 'Berkas tidak ditemukan di server.');

        return $disk->download($path, $name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff', // browser jangan menebak jenis file sendiri
        ]);
    }
}
