<?php

namespace Tests\Feature\Staff;

use App\Enums\TicketStatus;
use App\Models\Rating;
use App\Models\Ticket;
use Illuminate\Support\Carbon;

/**
 * Riwayat Tiket Staff (US-09, F-TKS-06): tiket Selesai/Ditolak yang pernah ia tangani, filter bulan/status/kelompok,
 * detail dengan bukti, rating, dan keterangan SLA.
 */
class StaffHistoryTest extends StaffTestCase
{
    private function closed(TicketStatus $status, string $title, Carbon $resolvedAt, array $attributes = []): Ticket
    {
        return $this->inProgress($attributes['handler'] ?? $this->akademik, $attributes['category'] ?? null, [
            'title' => $title,
            'status' => $status,
            'created_at' => $resolvedAt->copy()->subHours($attributes['hours'] ?? 10),
            'resolved_at' => $resolvedAt,
            'rejection_reason' => $status === TicketStatus::Ditolak ? 'Data pelapor tidak valid' : null,
        ]);
    }

    public function test_lists_only_my_closed_tickets(): void
    {
        $this->closed(TicketStatus::Selesai, 'Selesai milik saya', now());
        $this->closed(TicketStatus::Ditolak, 'Ditolak milik saya', now());
        $this->closed(TicketStatus::Selesai, 'Selesai milik Rudi', now(), ['handler' => $this->akademik2]);
        $this->inProgress($this->akademik, null, ['title' => 'Masih diproses']);

        $this->actingAs($this->akademik)->get('/staff/history')
            ->assertOk()
            ->assertSee('Selesai milik saya')
            ->assertSee('Ditolak milik saya')
            ->assertSee('Alasan: Data pelapor tidak valid')
            ->assertDontSee('Selesai milik Rudi')
            ->assertDontSee('Masih diproses');
    }

    public function test_filters_month_status_and_group(): void
    {
        $this->akademik->specialties()->attach($this->cat('Keuangan & UKT'));
        $this->closed(TicketStatus::Selesai, 'Oktober selesai', Carbon::parse('2026-10-10 10:00'));
        $this->closed(TicketStatus::Ditolak, 'September ditolak', Carbon::parse('2026-09-20 10:00'));
        $this->closed(TicketStatus::Selesai, 'UKT selesai', Carbon::parse('2026-10-12 10:00'), ['category' => $this->cat('Keuangan & UKT')]);

        $get = fn (array $query) => $this->actingAs($this->akademik)->get('/staff/history?'.http_build_query($query));

        $get(['month' => '2026-10'])->assertSee('Oktober selesai')->assertSee('UKT selesai')->assertDontSee('September ditolak');
        $get(['status' => 'ditolak'])->assertSee('September ditolak')->assertDontSee('Oktober selesai');
        $get(['group' => $this->cat('Keuangan & UKT')->id])->assertSee('UKT selesai')->assertDontSee('Oktober selesai');
        $get(['month' => 'bukan-bulan', 'status' => 'menunggu'])->assertOk()->assertSee('Oktober selesai')->assertSee('September ditolak');
    }

    public function test_detail_shows_proofs_rating_and_sla(): void
    {
        $onTime = $this->closed(TicketStatus::Selesai, 'Tepat waktu', now(), ['hours' => 10]);  // KRS 24 jam
        $late = $this->closed(TicketStatus::Selesai, 'Terlambat', now(), ['hours' => 30]);
        $onTime->resolutionProofs()->create(['file_name' => 'bukti.pdf', 'file_path' => 'proofs/x.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100, 'uploaded_by' => $this->akademik->id]);
        Rating::create(['ticket_id' => $onTime->id, 'user_id' => $this->mahasiswa->id, 'score' => 5, 'comment' => 'Mantap']);

        $this->actingAs($this->akademik)->get("/staff/history/{$onTime->id}")
            ->assertOk()
            ->assertSee('Bukti penyelesaian')
            ->assertSee('bukti.pdf')
            ->assertSee('Sesuai SLA')
            ->assertSee('5 dari 5 bintang')
            ->assertSee('Mantap')
            ->assertSee('Tiket sudah ditutup, thread tidak dapat dibalas lagi.')
            ->assertDontSee('Tutup tiket');

        $this->actingAs($this->akademik)->get("/staff/history/{$late->id}")
            ->assertSee('Melewati SLA')->assertSee('Pelapor belum memberi rating.');

        $this->actingAs($this->akademik)->get('/staff/history')->assertSee('Sesuai SLA')->assertSee('Melewati SLA');
    }

    public function test_open_and_closed_detail_urls_redirect_to_the_right_menu(): void
    {
        $open = $this->inProgress();
        $closed = $this->closed(TicketStatus::Ditolak, 'Ditolak', now());

        $this->actingAs($this->akademik)->get("/staff/history/{$open->id}")->assertRedirect("/staff/tickets/{$open->id}");
        $this->actingAs($this->akademik)->get("/staff/tickets/{$closed->id}")->assertRedirect("/staff/history/{$closed->id}");
    }

    public function test_empty_history(): void
    {
        $this->actingAs($this->umum)->get('/staff/history')->assertOk()->assertSee('Belum ada riwayat');
    }
}
