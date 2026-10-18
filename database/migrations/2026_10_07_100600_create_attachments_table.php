<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel 8: attachments — lampiran dari pelapor / thread.
 * Diisi SALAH SATU: ticket_id (lampiran form awal) ATAU comment_id (lampiran pesan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path', 500);
            $table->string('mime_type', 50);          // image/jpeg, image/png, application/pdf
            $table->unsignedInteger('file_size');     // bytes, maks 5MB (divalidasi di aplikasi)
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
