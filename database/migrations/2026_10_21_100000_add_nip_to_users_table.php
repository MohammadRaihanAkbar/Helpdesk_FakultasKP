<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [M5] Kolom users.nip — identitas utama dosen.
 *
 * Kenapa kolom baru, bukan mengganti nama kolom nidn?
 *   - Keputusan terbaru: dosen diidentifikasi dengan NIP (Bridge menulis field login "NIM/NIP/NIK").
 *   - Sampai M4, kolom nidn ikut menyimpan NIP bila SSO tidak memberi NIDN. Namanya jadi menyesatkan.
 *   - Migration lama TIDAK diubah (sudah dijalankan di database Laragon/server). Perubahan skema selalu
 *     lewat migration baru, supaya cukup "php artisan migrate" tanpa migrate:fresh.
 *   - Kolom nidn tetap ada: diisi hanya kalau SSO benar-benar mengirim NIDN.
 *
 * Data lama: nilai 18 digit di kolom nidn (= NIP PNS) dipindah ke kolom nip.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nip', 20)->nullable()->unique()->after('nidn'); // khusus dosen (NIP PNS 18 digit)
        });

        // Dikerjakan di PHP (bukan REGEXP SQL) supaya sama di MySQL dan SQLite.
        DB::table('users')->whereNotNull('nidn')->orderBy('id')->get(['id', 'nidn'])
            ->filter(fn ($user) => preg_match('/^\d{18}$/', $user->nidn))
            ->each(fn ($user) => DB::table('users')->where('id', $user->id)->update(['nip' => $user->nidn, 'nidn' => null]));
    }

    public function down(): void
    {
        // Kembalikan NIP ke kolom nidn (perilaku M4) untuk akun yang tidak punya NIDN.
        DB::table('users')->whereNotNull('nip')->whereNull('nidn')->orderBy('id')->get(['id', 'nip'])
            ->each(fn ($user) => DB::table('users')->where('id', $user->id)->update(['nidn' => $user->nip]));

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nip']);
            $table->dropColumn('nip');
        });
    }
};
