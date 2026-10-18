<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [BARU v6.1] Tabel 4: category_audiences — siapa yang boleh MELIHAT sebuah Kelompok Layanan.
 *
 * Contoh: "Integritas Dosen" hanya punya 1 baris (mahasiswa) -> dosen & alumni tidak melihatnya.
 *         "Akun, Jaringan & IT" punya 2 baris (mahasiswa, dosen).
 *
 * Baris hanya dibuat untuk Kelompok Layanan (parent_id = NULL).
 * Jenis Masalah (anak) otomatis MEWARISI audiens induknya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->enum('user_type', ['mahasiswa', 'dosen', 'alumni']);

            // 1 kategori tidak boleh punya audiens yang sama 2x
            $table->unique(['category_id', 'user_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_audiences');
    }
};
