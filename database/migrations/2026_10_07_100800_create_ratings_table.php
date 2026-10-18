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

        // Lapisan pengaman di database: skor hanya boleh 1 sampai 5
        DB::statement('ALTER TABLE ratings ADD CONSTRAINT ratings_score_check CHECK (score BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
