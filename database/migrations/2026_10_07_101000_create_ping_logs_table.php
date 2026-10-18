<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [BARU v6.1] Tabel 12: ping_logs — riwayat teguran (Ping) Admin Fakultas ke Staff.
 *
 * Dua kolom menunjuk ke tabel users dengan peran berbeda (mirip reporter_id & handler_id di tickets):
 *   admin_id = pengirim Ping (role = admin)
 *   staff_id = penerima Ping (role = staff)
 * 1x Ping ke 3 staff = 3 baris.
 * Notifikasinya tetap dicatat di tabel notifications; tabel ini khusus jejak aksi manajerialnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ping_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('staff_id')->constrained('users')->restrictOnDelete();
            $table->text('message')->nullable(); // pesan kustom opsional
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ping_logs');
    }
};
