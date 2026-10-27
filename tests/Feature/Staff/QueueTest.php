<?php

namespace Tests\Feature\Staff;

use App\Enums\TicketStatus;
use App\Enums\UserType;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Support\Carbon;

/**
 * Antrean Tiket Staff (US-07, F-TKS-01, PRD 4.6): hanya tiket Menunggu, fakultas sendiri, kelompok spesialisasi.
 */
class QueueTest extends StaffTestCase
{
    public function test_queue_shows_only_waiting_tickets_in_own_faculty_and_specialty(): void
    {
        $krs = $this->waiting(null, ['title' => 'KRS belum di-ACC (antrean)']);                          // Jenis Masalah di bawah Sistem Akademik
        $this->waiting($this->cat('Fasilitas, Sarana & Prasarana'), ['title' => 'Proyektor rusak']);    // kelompok lain
        $this->inProgress($this->akademik2, null, ['title' => 'Sudah diambil Rudi']);                  // bukan Menunggu
        $this->ticketFor($this->mahasiswa, $this->cat('KRS belum di-ACC', $this->febi), ['title' => 'KRS FEBI']); // fakultas lain

        $this->actingAs($this->akademik)->get('/staff/queue')
            ->assertOk()
            ->assertSee('KRS belum di-ACC (antrean)')
            ->assertSee('Ahmad Mahasiswa')
            ->assertSee(route('staff.tickets.assign', $krs))
            ->assertDontSee('Proyektor rusak')
            ->assertDontSee('Sudah diambil Rudi')
            ->assertDontSee('KRS FEBI');

        // Staff FEBI dengan kelompok bernama sama tidak melihat tiket FITK.
        $this->actingAs($this->staffFebi)->get('/staff/queue')
            ->assertOk()->assertSee('KRS FEBI')->assertDontSee('KRS belum di-ACC (antrean)');
    }

    public function test_queue_is_fifo_oldest_first(): void
    {
        $this->waiting(null, ['title' => 'Tiket baru', 'created_at' => now()->subHour()]);
        $this->waiting(null, ['title' => 'Tiket paling lama', 'created_at' => now()->subDays(2)]);
        $this->waiting(null, ['title' => 'Tiket kemarin', 'created_at' => now()->subDay()]);

        $this->actingAs($this->akademik)->get('/staff/queue')
            ->assertSeeInOrder(['Tiket paling lama', 'Tiket kemarin', 'Tiket baru']);
    }

    public function test_sla_badges_red_and_yellow(): void
    {
        // KRS: SLA 24 jam. 30 jam lalu = lewat; 20 jam lalu = sisa 4 jam (< 25% dari 24 = 6 jam) = mendekati.
        $this->waiting(null, ['title' => 'Lewat SLA', 'created_at' => now()->subHours(30)]);

        $this->actingAs($this->akademik)->get('/staff/queue')->assertSee('Melewati Target')->assertDontSee('Mendekati Batas Waktu');

        Ticket::query()->update(['created_at' => now()->subHours(20)]);
        $this->actingAs($this->akademik)->get('/staff/queue')->assertSee('Mendekati Batas Waktu')->assertDontSee('Melewati Target');

        Ticket::query()->update(['created_at' => now()->subHours(2)]);
        $this->actingAs($this->akademik)->get('/staff/queue')->assertDontSee('Mendekati Batas Waktu')->assertDontSee('Melewati Target');
    }

    public function test_filters_group_reporter_type_date_and_search(): void
    {
        $this->umum->specialties()->attach($this->cat('Akun, Jaringan & IT'));
        $dosen = User::factory()->dosen($this->fitk)->create();

        $this->waiting($this->cat('Fasilitas, Sarana & Prasarana'), ['title' => 'AC kelas mati', 'created_at' => Carbon::parse('2026-10-01 09:00')]);
        $wifi = $this->ticketFor($dosen, $this->cat('Akun, Jaringan & IT'), ['title' => 'Wi-Fi dosen mati', 'created_at' => Carbon::parse('2026-10-05 09:00')]);

        $get = fn (array $query) => $this->actingAs($this->umum)->get('/staff/queue?'.http_build_query($query));

        $get(['group' => $this->cat('Akun, Jaringan & IT')->id])->assertSee('Wi-Fi dosen mati')->assertDontSee('AC kelas mati');
        $get(['type' => UserType::Dosen->value])->assertSee('Wi-Fi dosen mati')->assertDontSee('AC kelas mati');
        $get(['from' => '2026-10-03'])->assertSee('Wi-Fi dosen mati')->assertDontSee('AC kelas mati');
        $get(['to' => '2026-10-02'])->assertSee('AC kelas mati')->assertDontSee('Wi-Fi dosen mati');
        $get(['q' => $wifi->ticket_number])->assertSee('Wi-Fi dosen mati')->assertDontSee('AC kelas mati');
        $get(['q' => 'kelas'])->assertSee('AC kelas mati')->assertDontSee('Wi-Fi dosen mati');

        // Kelompok di luar spesialisasi diabaikan (tidak bisa "mengintip" antrean kelompok lain lewat URL).
        $this->waiting(null, ['title' => 'KRS milik akademik']);
        $get(['group' => $this->cat('Sistem Akademik & Perkuliahan')->id])->assertSee('AC kelas mati')->assertDontSee('KRS milik akademik');
    }

    public function test_staff_without_specialty_sees_empty_queue_with_message(): void
    {
        $this->waiting();

        $this->actingAs($this->tanpaSpesialisasi)->get('/staff/queue')
            ->assertOk()
            ->assertSee('Anda belum memiliki spesialisasi layanan')
            ->assertDontSee('Ambil Tiket');
    }

    public function test_inactive_staff_is_logged_out_and_has_no_queue(): void
    {
        $this->waiting();

        $this->actingAs($this->nonaktif)->get('/staff/queue')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertSame(0, Ticket::forStaff($this->nonaktif)->count());
    }

    public function test_sensitive_reporter_is_hidden_in_queue(): void
    {
        $this->waiting($this->cat('Integritas Dosen'), ['title' => 'Dosen tidak hadir']);

        $this->actingAs($this->dekanat)->get('/staff/queue')
            ->assertSee('Dosen tidak hadir')
            ->assertSee('Identitas dirahasiakan')
            ->assertSee('Sensitif')
            ->assertDontSee('Ahmad Mahasiswa');
    }

    public function test_other_roles_cannot_open_staff_pages(): void
    {
        foreach ([$this->mahasiswa, $this->admin] as $user) {
            foreach (['/staff/queue', '/staff/tickets', '/staff/history', '/staff/dashboard'] as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
        }
    }

    public function test_menu_counts_queue_and_my_tickets(): void
    {
        $this->waiting();
        $this->waiting();
        $this->inProgress($this->akademik);

        $counts = collect(Navigation::for($this->akademik))->pluck('count', 'label');

        $this->assertSame(['Dashboard', 'Antrean Tiket', 'Tiket Saya', 'Riwayat Tiket', 'Laporan Kerja'], $counts->keys()->all()); // M6: + Laporan Kerja
        $this->assertSame(2, $counts['Antrean Tiket']);
        $this->assertSame(1, $counts['Tiket Saya']);
    }

    public function test_top_bar_search_goes_to_queue(): void
    {
        $this->actingAs($this->akademik)->get('/staff/dashboard')
            ->assertSee('action="'.route('staff.queue').'"', false);
        $this->assertSame(TicketStatus::Menunggu, $this->waiting()->status);
    }
}
