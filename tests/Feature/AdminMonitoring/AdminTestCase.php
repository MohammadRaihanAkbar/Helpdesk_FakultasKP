<?php

namespace Tests\Feature\AdminMonitoring;

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Rating;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\Feature\Staff\StaffTestCase;

/**
 * Bekal tes fitur Admin Fakultas M4 (dashboard, Monitor Antrean, Ping, laporan & ekspor).
 * Di atas bekal StaffTestCase (FITK, FEBI, $admin FITK, staff FITK berbagai spesialisasi, $staffFebi):
 *
 *   $adminFebi  Admin Fakultas FEBI (untuk uji lintas fakultas)
 *
 * Helper ticket() membuat tiket dengan waktu yang DITENTUKAN (created/assigned/resolved), supaya hasil
 * perhitungan KPI bisa dihitung manual di tes.
 */
abstract class AdminTestCase extends StaffTestCase
{
    protected User $adminFebi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminFebi = User::factory()->admin($this->febi)->create(['name' => 'Admin FEBI']);
    }

    /**
     * @param  array{status?: TicketStatus, created?: string, claimAfter?: float, resolveAfter?: float, handler?: User, category?: Category, rating?: int, title?: string, reporter?: User}  $o
     *                                                                                                                                                                                           claimAfter / resolveAfter = jam setelah created
     */
    protected function ticket(array $o = []): Ticket
    {
        $status = $o['status'] ?? TicketStatus::Menunggu;
        $created = Carbon::parse($o['created'] ?? now());
        $claimed = $status !== TicketStatus::Menunggu;
        $closed = in_array($status, TicketStatus::closedStatuses(), true);

        $ticket = $this->ticketFor($o['reporter'] ?? $this->mahasiswa, $o['category'] ?? null, array_filter([
            'title' => $o['title'] ?? null,
            'status' => $status,
            'created_at' => $created,
            'handler_id' => $claimed ? ($o['handler'] ?? $this->akademik)->id : null,
            'assigned_at' => $claimed ? $created->copy()->addMinutes((int) round(($o['claimAfter'] ?? 1) * 60)) : null,
            'resolved_at' => $closed ? $created->copy()->addMinutes((int) round(($o['resolveAfter'] ?? 10) * 60)) : null,
            'rejection_reason' => $status === TicketStatus::Ditolak ? 'Data pelapor tidak valid' : null,
        ], fn ($value) => $value !== null));

        if (isset($o['rating'])) {
            Rating::create(['ticket_id' => $ticket->id, 'user_id' => $ticket->reporter_id, 'score' => $o['rating']]);
        }

        return $ticket;
    }
}
