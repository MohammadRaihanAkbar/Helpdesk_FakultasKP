<?php

namespace App\Http\Controllers;

use App\Services\CategoryCatalogService;
use App\Services\Reports\LandingStatistics;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * [M6] GET / — halaman depan publik (sebelum login): profil Helpdesk Fakultas Terpadu, cara kerja, katalog layanan
 * standar, dan statistik "Tren Tiket" (agregat saja, lihat LandingStatistics).
 *
 * Terbuka untuk tamu MAUPUN yang sudah login (tanpa middleware guest): tamu melihat tombol "Masuk",
 * pengguna yang sudah login melihat "Buka Dashboard" ke dashboard perannya.
 * Tautan registrasi alumni (hidden link) sengaja TIDAK ditampilkan di sini.
 */
class LandingController extends Controller
{
    public function __invoke(Request $request, LandingStatistics $statistics): View
    {
        $user = $request->user();

        return view('landing', [
            'stats' => $statistics->get(),
            'catalog' => CategoryCatalogService::CATALOG,
            'dashboardUrl' => $user ? route($user->role->homeRoute()) : null,
            'contactEmail' => config('helpdesk.landing.contact_email'),
        ]);
    }
}
