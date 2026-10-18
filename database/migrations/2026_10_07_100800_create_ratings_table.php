<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel 10: ratings — skor kepuasan (CSAT). Relasi 1:1 dengan tickets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            // unique() inilah yang membuat relasi menjadi 1:1 (1 tiket = 1 rating)
            $table->foreignId('ticket_id')->unique()->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('score'); // 1 - 5
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Lapisan pengaman di database: skor hanya boleh 1 sampai 5.
        //
        // Dilewati di SQLite: SQLite tidak mendukung "ALTER TABLE ... ADD CONSTRAINT"
        // (CHECK hanya bisa ditulis saat CREATE TABLE), sehingga baris ini membuat
        // migration gagal saat test (phpunit.xml memakai SQLite in-memory).
        // Di MySQL/MariaDB (Laragon & server) constraint tetap dibuat. Aturan 1-5 juga
        // divalidasi di aplikasi (form rating, M2), jadi test tetap menjaga aturannya.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE ratings ADD CONSTRAINT ratings_score_check CHECK (score BETWEEN 1 AND 5)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
