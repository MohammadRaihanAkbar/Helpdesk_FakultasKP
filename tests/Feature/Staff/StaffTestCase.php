<?php

namespace Tests\Feature\Staff;

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Tickets\TicketTestCase;

/**
 * Bekal bersama tes alur Staff (M3). Di atas bekal TicketTestCase (FITK, FEBI, mahasiswa FITK, disk palsu):
 *
 *   $akademik  staff FITK, spesialisasi "Sistem Akademik & Perkuliahan"
 *   $akademik2 staff FITK kedua dengan spesialisasi SAMA (untuk uji balapan & "hanya melihat")
 *   $umum      staff FITK, spesialisasi "Fasilitas, Sarana & Prasarana"
 *   $dekanat   staff FITK, spesialisasi "Integritas Dosen" (sensitif)
 *   $staffFebi staff FEBI, spesialisasi "Sistem Akademik & Perkuliahan" milik FEBI
 *   $tanpaSpesialisasi  staff FITK tanpa spesialisasi
 *   $nonaktif  staff FITK nonaktif, spesialisasi "Sistem Akademik & Perkuliahan"
 *   $admin     Admin Fakultas FITK
 */
abstract class StaffTestCase extends TicketTestCase
{
    protected User $akademik;

    protected User $akademik2;

    protected User $umum;

    protected User $dekanat;

    protected User $staffFebi;

    protected User $tanpaSpesialisasi;

    protected User $nonaktif;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->akademik = $this->staffWith('Sari Akademik', 'Sistem Akademik & Perkuliahan');
        $this->akademik2 = $this->staffWith('Rudi Akademik', 'Sistem Akademik & Perkuliahan');
        $this->umum = $this->staffWith('Umar Umum', 'Fasilitas, Sarana & Prasarana');
        $this->dekanat = $this->staffWith('Dewi Dekanat', 'Integritas Dosen');
        $this->staffFebi = User::factory()->staff($this->febi)->create(['name' => 'Fajar FEBI']);
        $this->staffFebi->specialties()->attach($this->cat('Sistem Akademik & Perkuliahan', $this->febi));
        $this->tanpaSpesialisasi = User::factory()->staff($this->fitk)->create(['name' => 'Tono Tanpa']);
        $this->nonaktif = $this->staffWith('Nina Nonaktif', 'Sistem Akademik & Perkuliahan', ['is_active' => false]);
        $this->admin = User::factory()->admin($this->fitk)->create(['name' => 'Admin FITK']);
    }

    protected function staffWith(string $name, string $group, array $attributes = []): User
    {
        $staff = User::factory()->staff($this->fitk)->create(['name' => $name] + $attributes);
        $staff->specialties()->attach($this->cat($group));

        return $staff;
    }

    /** Tiket Menunggu dari mahasiswa (default: KRS belum di-ACC, kelompok Sistem Akademik). */
    protected function waiting(?Category $category = null, array $attributes = []): Ticket
    {
        return $this->ticketFor($this->mahasiswa, $category, $attributes);
    }

    /** Tiket yang sudah diambil $handler (default staff akademik). */
    protected function inProgress(?User $handler = null, ?Category $category = null, array $attributes = []): Ticket
    {
        return $this->ticketFor($this->mahasiswa, $category, $attributes + [
            'status' => TicketStatus::Diproses,
            'handler_id' => ($handler ?? $this->akademik)->id,
            'assigned_at' => now(),
        ]);
    }

    protected function png(string $name = 'bukti.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 20, 20);
    }
}