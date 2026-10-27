<?php

namespace App\Notifications;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/** [M6] Ke alumni pendaftar: akun disetujui (bisa login) atau ditolak (dengan alasan). */
class AlumniVerifiedMail extends HelpdeskMail
{
    public AccountStatus $status;

    public ?string $reason;

    public string $facultyCode;

    public function __construct(User $alumni, public ?string $registrationUrl = null)
    {
        parent::__construct();

        $this->status = $alumni->account_status;
        $this->reason = $alumni->rejection_reason;
        $this->facultyCode = (string) $alumni->originFaculty?->code;
    }

    public function subject(): string
    {
        return $this->status === AccountStatus::Approved
            ? 'Akun Helpdesk Fakultas Anda sudah disetujui'
            : 'Pendaftaran akun Helpdesk Fakultas Anda ditolak';
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = $this->message($notifiable);

        if ($this->status === AccountStatus::Approved) {
            return $mail
                ->line("Admin Fakultas {$this->facultyCode} sudah memverifikasi pendaftaran Anda. Akun Anda sekarang **aktif**.")
                ->line('Masuk dengan **NIM lama** atau **email ini**, beserta password yang Anda buat saat mendaftar.')
                ->action('Masuk ke Helpdesk', route('login'));
        }

        $mail->line("Mohon maaf, Admin Fakultas {$this->facultyCode} **menolak** pendaftaran akun Anda.")
            ->line(self::block(['Alasan' => (string) $this->reason]))
            ->line('Jika data yang Anda isi keliru, silakan mendaftar ulang dengan data yang benar (NIM dan email yang sama boleh dipakai lagi).');

        return $this->registrationUrl ? $mail->action('Daftar Ulang', $this->registrationUrl) : $mail;
    }
}
