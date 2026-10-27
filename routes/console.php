<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Jadwal (scheduler) — M6
|--------------------------------------------------------------------------
|
| Supaya jadwal di bawah benar-benar berjalan, harus ada yang "membangunkan" scheduler setiap menit:
|   Laragon (development) : buka terminal, jalankan  php artisan schedule:work   (biarkan terbuka)
|   Server (production)   : cron  * * * * * cd /path/ke/proyek && php artisan schedule:run >> /dev/null 2>&1
| Lihat daftar jadwal: php artisan schedule:list
|
*/

// Pengingat rating tiket Selesai yang belum dinilai (RatingReminderService). withoutOverlapping(): kalau
// proses sebelumnya belum selesai, jadwal berikutnya dilewati (perintahnya sendiri juga sudah idempotent).
Schedule::command('helpdesk:pengingat-rating')->hourly()->withoutOverlapping();
