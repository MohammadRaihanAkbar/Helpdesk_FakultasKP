<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan 2 kolom relasi ke fakultas pada tabel users.
 *
 * Kenapa terpisah? Migration users berjalan PALING AWAL (bawaan Laravel),
 * sedangkan foreign key hanya bisa dibuat jika tabel tujuannya (faculties) sudah ada.
 *
 * Dua kolom ini sama-sama menunjuk ke tabel faculties, tapi ARTINYA BEDA:
 *   - origin_faculty_id = fakultas ASAL pelapor (mahasiswa/dosen/alumni)   -> relasi "origin_of"
 *   - faculty_id        = fakultas TEMPAT BERTUGAS Staff/Admin Fakultas     -> relasi "has"
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // [BARU v6.1] Fakultas asal pelapor. Wajib untuk alumni hidden link (<= 2021),
            // dari data SSO untuk mahasiswa/dosen/alumni >= 2022. Dipakai untuk pre-fill Fakultas Tujuan.
            $table->foreignId('origin_faculty_id')
                ->nullable()
                ->after('graduation_year')
                ->constrained('faculties')
                ->nullOnDelete();

            // Fakultas penugasan: NULL untuk Super Admin & End-User; diisi untuk Staff & Admin Fakultas
            $table->foreignId('faculty_id')
                ->nullable()
                ->after('origin_faculty_id')
                ->constrained('faculties')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('faculty_id');
            $table->dropConstrainedForeignId('origin_faculty_id');
        });
    }
};
