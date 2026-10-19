<?php

namespace App\Http\Controllers\Admin;

use App\Services\StaffSpecialtyService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard Admin Fakultas. M2: pintasan ke Kelola Staff & Pengaturan Layanan + peringatan "Kelompok tanpa Staff".
 * Kartu operasional, SLA compliance, dan grafik tren menyusul di M4.
 */
class DashboardController extends AdminController
{
    public function __invoke(Request $request, StaffSpecialtyService $specialties): View
    {
        $user = $request->user()->load('faculty', 'originFaculty');

        return view('admin.dashboard', [
            'user' => $user,
            'coverage' => $user->faculty_id ? $specialties->coverage($user->faculty_id) : collect(),
        ]);
    }
}
