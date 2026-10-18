<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel 6: tickets — transaksi utama aduan (tanpa auto-expire).
 *
 * Aturan konsistensi data (dijaga di aplikasi & seeder):
 *   menunggu -> handler_id & assigned_at KOSONG
 *   diproses -> handler_id & assigned_at TERISI
 *   selesai  -> + resolved_at terisi, minimal 1 resolution_proof
 *   ditolak  -> + resolved_at & rejection_reason terisi (hanya bisa dari 'diproses')
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 30)->unique(); // TKT-YYYYMM-XXXX

            $table->foreignId('faculty_id')->constrained('faculties')->restrictOnDelete();

            $table->string('title', 150); // [v6.1] 150, sama dengan batas form
            $table->text('description');

            // Hanya 4 status (status 'kedaluwarsa' dihapus di v6.0)
            $table->enum('status', ['menunggu', 'diproses', 'selesai', 'ditolak'])->default('menunggu');
            $table->text('rejection_reason')->nullable(); // wajib diisi jika status = ditolak

            // Dua relasi ke tabel users yang SAMA, dengan peran berbeda
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete(); // pembuat tiket

            // [BARU v6.1] SNAPSHOT jenis pelapor saat tiket dibuat (disalin dari users.user_type).
            // Laporan per jenis pelapor membaca kolom ini, jadi data lama tidak bergeser
            // walau mahasiswa nanti lulus dan user_type-nya berubah jadi alumni.
            $table->enum('reporter_type', ['mahasiswa', 'dosen', 'alumni']);

            $table->foreignId('handler_id')->nullable()->constrained('users')->nullOnDelete(); // staff penangan

            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete(); // [v6.1] kategori LEAF (yang bisa dipilih)

            // SNAPSHOT SLA: disalin dari categories.sla_hours saat tiket dibuat
            $table->unsignedInteger('sla_target_hours');

            $table->timestamp('assigned_at')->nullable();        // [BARU v6.1] waktu staff mengambil tiket (bareng handler_id)
            $table->timestamp('first_responded_at')->nullable(); // balasan pertama staff
            $table->timestamp('resolved_at')->nullable();        // [v6.1] waktu tiket DITUTUP: selesai MAUPUN ditolak
            $table->timestamps();

            // Index untuk mempercepat filter antrean & dashboard
            $table->index(['faculty_id', 'status']);
            $table->index(['category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
