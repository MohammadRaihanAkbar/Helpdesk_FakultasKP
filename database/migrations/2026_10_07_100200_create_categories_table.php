<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel 3: categories — katalog layanan per fakultas + target SLA.
 *
 * [v6.1] Hierarki 2 tingkat lewat parent_id (relasi ke tabel ini SENDIRI):
 *   parent_id = NULL   -> Kelompok Layanan  (contoh: "Sistem Akademik & Perkuliahan")
 *   parent_id = terisi -> Jenis Masalah     (contoh: "KRS belum di-ACC")
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->constrained('faculties')->cascadeOnDelete();

            // [BARU v6.1] Self-FK. restrictOnDelete: kelompok yang masih punya anak tidak bisa dihapus
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();

            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedInteger('sla_hours')->default(72); // target SLA dalam jam

            // [BARU v6.1] TRUE = hasil seed Katalog Standar (tidak bisa dihapus / diganti nama)
            //             FALSE = kategori tambahan buatan Admin Fakultas
            $table->boolean('is_standard')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Tidak boleh ada nama kategori kembar di fakultas yang sama
            $table->unique(['faculty_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
