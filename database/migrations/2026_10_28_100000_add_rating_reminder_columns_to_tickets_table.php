<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [M6] Jejak pengingat rating di tabel tickets.
 *
 *   rating_reminder_count    berapa kali pengingat sudah dikirim (maks. config helpdesk.rating_reminder.max_reminders)
 *   last_rating_reminder_at  kapan pengingat terakhir dikirim (dasar jeda ke pengingat berikutnya)
 *
 * Disimpan di tiket (bukan tabel baru) karena hanya dua angka per tiket, dan perintah pengingat cukup membaca
 * satu tabel. Kenapa perlu dicatat? Supaya perintah yang berjalan tiap jam tidak mengirim pengingat yang sama
 * berulang-ulang (idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating_reminder_count')->default(0)->after('resolved_at');
            $table->timestamp('last_rating_reminder_at')->nullable()->after('rating_reminder_count');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['rating_reminder_count', 'last_rating_reminder_at']);
        });
    }
};
