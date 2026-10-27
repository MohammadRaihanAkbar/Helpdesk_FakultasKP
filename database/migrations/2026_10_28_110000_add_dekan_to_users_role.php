<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [M6] Peran baru "dekan" di kolom users.role.
 *
 * Kolom role dibuat sebagai ENUM di migration 0001_01_01_000000 (end_user, staff, admin, super_admin). Migration lama
 * TIDAK diubah (sudah dijalankan di Laragon); nilai baru ditambahkan di sini.
 *
 * ->change() (fitur bawaan Laravel 11+, tanpa paket doctrine/dbal) menghasilkan SQL yang cocok per database:
 *   MySQL  : ALTER TABLE users MODIFY role ENUM(..., 'dekan') NOT NULL DEFAULT 'end_user'
 *   SQLite : (dipakai tes) enum = TEXT + CHECK constraint. SQLite tidak bisa mengubah CHECK, jadi Laravel membuat ulang
 *            tabel users secara otomatis (salin data -> hapus tabel lama -> ganti nama). Data tetap utuh.
 */
return new class extends Migration
{
    private const OLD = ['end_user', 'staff', 'admin', 'super_admin'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [...self::OLD, 'dekan'])->default('end_user')->change();
        });
    }

    public function down(): void
    {
        // Dekan dikembalikan menjadi Pelapor dulu, supaya tidak ada nilai di luar daftar ENUM lama.
        DB::table('users')->where('role', 'dekan')->update(['role' => 'end_user', 'faculty_id' => null]);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', self::OLD)->default('end_user')->change();
        });
    }
};
