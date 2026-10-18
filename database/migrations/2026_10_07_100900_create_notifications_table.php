<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel 11: notifications — riwayat notifikasi (email & in-app) sesuai PRD.
 *
 * Catatan: struktur ini mengikuti PRD, BUKAN struktur tabel notifikasi bawaan Laravel
 * (php artisan make:notifications-table). Jadi notifikasi in-app nanti disimpan manual
 * lewat model, bukan lewat channel "database" bawaan Laravel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();       // penerima
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->cascadeOnDelete();
            $table->enum('type', ['email', 'in_app']);
            $table->string('subject', 200);
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('read_at')->nullable();

            $table->index(['user_id', 'is_read']); // mempercepat hitung "notifikasi belum dibaca"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
