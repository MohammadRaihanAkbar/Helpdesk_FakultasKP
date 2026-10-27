<?php

namespace App\Notifications;

use App\Http\Controllers\NotificationController;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Induk semua EMAIL notifikasi (M6, PRD bab 5 modul NOTIF + PRD 6.4).
 *
 * Kenapa class Notification bawaan Laravel (bukan Mail::to()->send() langsung)?
 *   - $user->notify(new TiketSelesai($ticket)) sudah tersedia di model User (RoutesNotifications sejak M1).
 *   - Alamat tujuan diambil dari User::routeNotificationForMail(): SATU tempat aturan "boleh dikirimi email?"
 *     (email kosong / email pengganti SSO -> tidak dikirim, cukup in-app).
 *   - Tinggal "implements ShouldQueue" supaya email masuk ANTREAN (tabel jobs) dan dikirim di background oleh
 *     `php artisan queue:work`. Pengguna tidak menunggu koneksi SMTP Gmail saat menekan tombol.
 *
 * Aturan PRD 6.4 "3x retry dengan exponential backoff":
 *   $tries = 3          -> paling banyak 3 kali percobaan
 *   backoff() [60, 300] -> jeda 1 menit sebelum percobaan ke-2, 5 menit sebelum ke-3
 *   Gagal 3x -> job masuk tabel failed_jobs (lihat: php artisan queue:failed, ulangi: php artisan queue:retry all).
 *
 * afterCommit(): email baru dimasukkan antrean SETELAH transaksi database selesai, jadi tidak ada email untuk
 * tiket yang ternyata batal disimpan.
 *
 * Notifikasi in-app (lonceng) TIDAK lewat class ini; tetap ditulis langsung ke tabel notifications versi PRD
 * oleh TicketNotifier (lihat catatan di model User tentang RoutesNotifications).
 */
abstract class HelpdeskMail extends Notification implements ShouldQueue
{
    use Queueable;

    /** Jumlah percobaan kirim (PRD 6.4). */
    public int $tries = 3;

    public function __construct()
    {
        $this->afterCommit();
    }

    /** Jeda (detik) antar percobaan: makin lama makin panjang (exponential backoff). */
    public function backoff(): array
    {
        return config('helpdesk.mail_backoff', [60, 300]);
    }

    /** Hanya channel mail, dan hanya untuk user yang punya email asli (User::canReceiveEmail()). */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && $notifiable->canReceiveEmail() ? ['mail'] : [];
    }

    abstract public function toMail(User $notifiable): MailMessage;

    /** Subjek email (juga dicatat di tabel notifications type = email, lihat LogSentEmail). */
    abstract public function subject(): string;

    /** Tiket yang dibicarakan email ini (null untuk email akun, mis. verifikasi alumni). */
    public function ticket(): ?Ticket
    {
        return null;
    }

    /** Kerangka email yang sama untuk semua notifikasi: subjek + sapaan "Halo, {nama depan}". */
    protected function message(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->greeting('Halo, '.self::firstName($notifiable->name).'!');
    }

    /** Halaman tiket yang cocok untuk peran penerima (sama dengan klik notifikasi lonceng). */
    protected function ticketUrl(User $notifiable, Ticket $ticket): string
    {
        return NotificationController::ticketUrl($notifiable, $ticket) ?? route('login');
    }

    /**
     * Beberapa pasangan "Label: isi" dalam SATU paragraf, satu pasangan per baris.
     *
     * MailMessage::line() biasa menggabungkan baris baru menjadi spasi, jadi dipakai HtmlString dengan
     * pemisah Markdown "dua spasi + enter" (= <br>). Isi dari pengguna (judul tiket, dll.) di-escape dengan e()
     * supaya tidak bisa menyisipkan HTML ke email.
     *
     * @param  array<string, string>  $rows
     */
    public static function block(array $rows): HtmlString
    {
        return new HtmlString(collect($rows)
            ->map(fn (string $value, string $label) => '**'.e($label).':** '.e($value))
            ->implode("  \n"));
    }

    protected static function firstName(string $name): string
    {
        $clean = preg_replace('/^((Dr|Prof|Ir|H|Hj|Dra|Drs)\.\s*)+/i', '', $name);

        return explode(' ', trim((string) $clean))[0] ?: $name;
    }
}
