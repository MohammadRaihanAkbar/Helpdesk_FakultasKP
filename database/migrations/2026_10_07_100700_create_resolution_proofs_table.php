<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel 9: resolution_proofs — bukti WAJIB dari staff sebelum tiket boleh "selesai".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resolution_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path', 500);
            $table->string('mime_type', 50);
            $table->unsignedInteger('file_size');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete(); // staff pengunggah
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resolution_proofs');
    }
};
