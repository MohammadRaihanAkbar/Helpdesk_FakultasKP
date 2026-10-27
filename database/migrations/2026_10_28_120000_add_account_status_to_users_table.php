<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [M6] Verifikasi akun alumni oleh Admin Fakultas.
 *
 *   account_status   pending | approved | rejected (lihat App\Enums\AccountStatus)
 *   verified_at      kapan disetujui / ditolak
 *   verified_by      Admin Fakultas yang memverifikasi (NULL kalau akunnya dihapus)
 *   rejection_reason alasan penolakan (wajib saat menolak, ditampilkan saat alumni mencoba login)
 *
 * PENTING: bawaan kolom = 'approved'. Artinya SEMUA akun yang sudah ada (termasuk alumni yang sudah mendaftar
 * sebelum M6) otomatis disetujui, jadi tidak ada yang tiba-tiba tidak bisa login setelah `php artisan migrate`.
 * Hanya pendaftaran alumni BARU yang diisi 'pending' oleh aplikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_status', ['pending', 'approved', 'rejected'])->default('approved')->after('is_active');
            $table->timestamp('verified_at')->nullable()->after('account_status');
            $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('verified_by');

            $table->index(['account_status', 'origin_faculty_id']); // daftar "menunggu verifikasi" per fakultas
        });

        // Ditulis eksplisit supaya jelas: akun lama = disetujui.
        DB::table('users')->update(['account_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_status', 'origin_faculty_id']);
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['account_status', 'verified_at', 'rejection_reason']);
        });
    }
};
