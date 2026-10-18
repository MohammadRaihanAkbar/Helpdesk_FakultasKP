<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel 7: comments — thread diskusi tiket.
 * [v6.1] Batas maksimal 20 pesan PUBLIK per tiket (internal note tidak dihitung) divalidasi di level APLIKASI, bukan database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->boolean('is_internal_note')->default(false); // true = hanya Staff & Admin yang bisa lihat
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
