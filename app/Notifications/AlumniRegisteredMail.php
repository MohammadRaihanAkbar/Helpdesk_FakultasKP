<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/** [M6] Ke Admin Fakultas asal: ada pendaftar alumni baru yang menunggu verifikasi. */
class AlumniRegisteredMail extends HelpdeskMail
{
    public function __construct(public User $alumni)
    {
        parent::__construct();
    }

    public function subject(): string
    {
        return "Pendaftaran alumni baru menunggu verifikasi: {$this->alumni->name}";
    }

    public function toMail(User $notifiable): MailMessage
    {
        return $this->message($notifiable)
            ->line('Ada alumni yang mendaftar lewat tautan registrasi alumni dan memilih fakultas Anda sebagai fakultas asal. Akunnya belum bisa dipakai sebelum Anda setujui.')
            ->line(self::block([
                'Nama' => $this->alumni->name,
                'NIM lama' => (string) $this->alumni->nim,
                'Tahun lulus' => (string) $this->alumni->graduation_year,
                'Email' => $this->alumni->email,
            ]))
            ->line('Cocokkan dengan data alumni fakultas sebelum menyetujui.')
            ->action('Buka Verifikasi Alumni', route('admin.alumni.index'));
    }
}
