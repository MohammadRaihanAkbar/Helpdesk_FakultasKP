<?php

namespace App\Console\Commands;

use App\Services\Tickets\RatingReminderService;
use Illuminate\Console\Command;

/**
 * [M6] php artisan helpdesk:pengingat-rating
 *
 * Mengirim pengingat (email + in-app) untuk tiket Selesai yang belum dirating. Dijadwalkan TIAP JAM di
 * routes/console.php, tetapi juga boleh dijalankan manual kapan saja: aturannya ada di RatingReminderService,
 * dan perintah ini idempotent (tidak mengirim pengingat yang sama dua kali).
 *
 *   php artisan helpdesk:pengingat-rating            kirim yang sudah jatuh tempo
 *   php artisan helpdesk:pengingat-rating --lihat    hanya tampilkan daftar, tidak mengirim apa pun
 *   php artisan helpdesk:pengingat-rating --paksa    untuk DEMO: abaikan jeda 24/48 jam (batas maks. pengingat tetap berlaku)
 */
class SendRatingReminders extends Command
{
    protected $signature = 'helpdesk:pengingat-rating
                            {--lihat : Hanya tampilkan tiket yang akan diingatkan, tanpa mengirim}
                            {--paksa : Demo: abaikan jeda waktu (batas jumlah pengingat tetap berlaku)}';

    protected $description = 'Kirim pengingat rating (email + in-app) untuk tiket Selesai yang belum dinilai pelapor';

    public function handle(RatingReminderService $reminders): int
    {
        $force = (bool) $this->option('paksa');

        if ($this->option('lihat')) {
            $due = $reminders->due(ignoreDelay: $force);
            $this->table(['Nomor tiket', 'Pelapor', 'Selesai', 'Pengingat ke-'], $due->map(fn ($ticket) => [
                $ticket->ticket_number,
                $ticket->reporter->name.($ticket->reporter->canReceiveEmail() ? '' : ' (tanpa email, in-app saja)'),
                $ticket->resolved_at->translatedFormat('j M Y H:i'),
                $ticket->rating_reminder_count + 1,
            ])->all());
            $this->info("{$due->count()} tiket akan diingatkan.");

            return self::SUCCESS;
        }

        $result = $reminders->send(ignoreDelay: $force);

        foreach ($result['tickets'] as $number) {
            $this->line("  • {$number}");
        }
        $this->info("Pengingat rating: {$result['sent']} dikirim, {$result['skipped']} dilewati, {$result['failed']} gagal.");

        if ($result['sent'] > 0) {
            $this->comment('Email masuk antrean. Pastikan `php artisan queue:work` berjalan (atau QUEUE_CONNECTION=sync).');
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
