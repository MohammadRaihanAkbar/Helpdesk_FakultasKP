<?php

namespace Tests\Feature\Staff;

use App\Enums\TicketStatus;
use App\Models\Rating;
use App\Models\Ticket;

/**
 * Dashboard Staff (US-06, PRD 10.3): 5 kartu PRD + rata-rata rating, daftar SLA, tiket yang sedang diproses.
 */
class StaffDashboardTest extends StaffTestCase
{
    public function test_cards_use_real_data(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));

        $this->waiting();
        $this->waiting();
        $this->waiting($this->cat('Fasilitas, Sarana & Prasarana')); // bukan spesialisasi
        $this->inProgress($this->akademik, null, ['title' => 'Sedang saya proses']);

        $today = $this->inProgress($this->akademik, null, ['status' => TicketStatus::Selesai, 'resolved_at' => now()->subHour()]);
        $this->inProgress($this->akademik, null, ['status' => TicketStatus::Selesai, 'resolved_at' => now()->subDays(3)]);
        $this->inProgress($this->akademik, null, ['status' => TicketStatus::Ditolak, 'resolved_at' => now()->subDays(2), 'rejection_reason' => 'x']);
        $this->inProgress($this->akademik, null, ['status' => TicketStatus::Ditolak, 'resolved_at' => now()->subMonth(), 'rejection_reason' => 'x']); // bulan lalu
        $this->inProgress($this->akademik2, null, ['status' => TicketStatus::Selesai, 'resolved_at' => now()]); // milik orang lain

        Rating::create(['ticket_id' => $today->id, 'user_id' => $this->mahasiswa->id, 'score' => 4]);
        $other = Ticket::where('status', TicketStatus::Selesai)->where('handler_id', $this->akademik->id)->whereKeyNot($today->id)->first();
        Rating::create(['ticket_id' => $other->id, 'user_id' => $this->mahasiswa->id, 'score' => 5]);

        $response = $this->actingAs($this->akademik)->get('/staff/dashboard')->assertOk();
        $stats = $response->viewData('stats');

        $this->assertSame(2, $stats['queue']);
        $this->assertSame(1, $stats['in_progress']);
        $this->assertSame(1, $stats['resolved_today']);
        $this->assertSame(1, $stats['rejected_month']);
        $this->assertSame(3, $stats['handled_month']); // 2 selesai + 1 ditolak bulan ini
        $this->assertSame(2, $stats['resolved_month']);
        $this->assertEquals(4.5, $stats['rating_avg']);
        $this->assertSame(2, $stats['rating_count']);

        $response->assertSee('Antrean')->assertSee('Sedang Saya Proses')->assertSee('Selesai Hari Ini')
            ->assertSee('Ditolak Bulan Ini')->assertSee('Ditangani Bulan Ini')->assertSee('Rata-rata Rating')
            ->assertSee('4,5')->assertSee('dari 2 rating')
            ->assertSee('Sedang saya proses');
    }

    public function test_sla_alert_list_marks_overdue_and_near(): void
    {
        $this->waiting(null, ['title' => 'Antrean lewat SLA', 'created_at' => now()->subHours(30)]);        // KRS 24 jam
        $this->inProgress($this->akademik, null, ['title' => 'Proses hampir lewat', 'created_at' => now()->subHours(20)]);
        $this->waiting(null, ['title' => 'Masih aman', 'created_at' => now()->subHour()]);

        $alerts = $this->actingAs($this->akademik)->get('/staff/dashboard')
            ->assertSee('Melewati SLA')->assertSee('Mendekati SLA')
            ->viewData('slaAlerts');

        $this->assertSame(['Antrean lewat SLA', 'Proses hampir lewat'], $alerts->pluck('title')->all());
    }

    public function test_empty_dashboard_for_staff_without_specialty(): void
    {
        $this->actingAs($this->tanpaSpesialisasi)->get('/staff/dashboard')
            ->assertOk()
            ->assertSee('Anda belum memiliki spesialisasi layanan')
            ->assertSee('Semua tiket masih dalam target SLA')
            ->assertSee('belum ada rating');
    }

    public function test_sensitive_reporter_hidden_in_sla_list(): void
    {
        $this->waiting($this->cat('Integritas Dosen'), ['title' => 'Kasus sensitif', 'created_at' => now()->subHours(200)]);

        $this->actingAs($this->dekanat)->get('/staff/dashboard')
            ->assertSee('Kasus sensitif')->assertSee('Identitas dirahasiakan')->assertDontSee('Ahmad Mahasiswa');
    }
}
