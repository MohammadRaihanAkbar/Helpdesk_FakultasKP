<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Services\FacultyService;
use App\Services\Reports\TicketMetrics;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /super-admin/dashboard — Dashboard Metrik Global (F-DSB-04, F-SPA-03, PRD 10.9).
 *
 * Isinya sengaja SEDERHANA (Super Admin tidak mengurus operasional tiket):
 *   - kartu: Fakultas Aktif, Total Tiket, CSAT, SLA Compliance se-universitas, Admin Fakultas
 *     -> rumus yang SAMA dengan dashboard Admin (TicketMetrics), hanya tanpa filter fakultas
 *   - ringkasan per fakultas (total, selesai, SLA Compliance, CSAT)
 *   - daftar fakultas dengan celah coverage Staff (US-17): hanya informasi, penugasan Staff wewenang Admin Fakultas
 *
 * Angka dihitung dari SEMUA tiket sejak awal (bukan per bulan), karena tujuannya gambaran umum.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, TicketMetrics $metrics, FacultyService $faculties): View
    {
        $rows = $metrics->rows(Ticket::query()); // tanpa where faculty_id = se-universitas
        $perFaculty = $rows->groupBy('faculty_id');
        $overview = $faculties->overview();

        return view('superadmin.dashboard', [
            'user' => $request->user()->load('faculty', 'originFaculty'),
            'kpi' => $metrics->summarize($rows),
            'cards' => [
                'faculties_active' => $overview->where('is_active', true)->count(),
                'faculties_total' => $overview->count(),
                'admins' => User::where('role', Role::Admin)->count(),
                'masuk_bulan_ini' => $rows->filter(fn (Ticket $ticket) => $ticket->created_at->greaterThanOrEqualTo(now()->startOfMonth()))->count(),
            ],
            'facultyStats' => $overview->map(fn ($faculty) => [
                'faculty' => $faculty,
                'kpi' => $metrics->summarize($perFaculty[$faculty->id] ?? []),
            ]),
            'gaps' => $faculties->coverageGaps(),
        ]);
    }
}
