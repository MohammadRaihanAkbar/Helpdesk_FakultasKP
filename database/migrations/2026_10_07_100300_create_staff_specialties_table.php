<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel 5: staff_specialties — tabel PIVOT (Many-to-Many) Staff <-> Kategori.
 * Menentukan antrean tiket mana yang boleh dilihat setiap staff.
 *
 * [v6.1] category_id HANYA boleh Kelompok Layanan (parent_id = NULL), divalidasi di aplikasi.
 * Spesialisasi "Sistem Akademik & Perkuliahan" otomatis mencakup semua Jenis Masalah di bawahnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_specialties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent(); // PRD: hanya created_at

            // Satu staff tidak boleh dipetakan 2x ke kategori yang sama
            $table->unique(['staff_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_specialties');
    }
};
