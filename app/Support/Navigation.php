<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Menu navigasi per peran (PRD 7.5), didefinisikan di SATU tempat.
 *
 * Layout aplikasi (sidebar, laci tablet, dan bottom nav HP) membaca menu dari sini.
 * Sebuah item hanya tampil kalau route-nya sudah ada (Route::has), jadi menu ikut
 * bertambah sendiri ketika route baru ditambahkan di milestone berikutnya. Di M1
 * baru "Dashboard" yang punya route. Hal yang sama berlaku untuk kotak pencarian di top bar.
 */
class Navigation
{
    /**
     * Bottom nav di HP maksimal 5 tombol supaya tiap tombol tetap ≥ 48px.
     * Kalau menunya kurang dari 5, sisa tempat diisi tombol Keluar (mockup A).
     */
    public const MOBILE_LIMIT = 5;

    /**
     * Urutan tombol bottom nav: menu bertanda 'fab' (Buat Tiket) dipindah ke TENGAH dan
     * tampil sebagai tombol emas bulat, seperti mockup design/a-dashboard-mobile.png.
     *
     * @param  list<array<string, mixed>>  $menu  hasil Navigation::for()
     * @return list<array<string, mixed>>
     */
    public static function mobile(array $menu): array
    {
        $menu = array_slice($menu, 0, self::MOBILE_LIMIT);
        $fab = array_values(array_filter($menu, fn (array $item) => $item['fab'] ?? false));
        $rest = array_values(array_filter($menu, fn (array $item) => ! ($item['fab'] ?? false)));

        if ($fab !== []) {
            // Tengah dihitung bersama tombol Keluar yang akan ditambahkan layout.
            $slots = count($menu) + (count($menu) < self::MOBILE_LIMIT ? 1 : 0);
            array_splice($rest, intdiv($slots, 2), 0, $fab);
        }

        return $rest;
    }

    /**
     * Item menu yang boleh dilihat user, beserta status aktifnya.
     *
     * @return list<array{route: string, label: string, icon: string, url: string, active: bool}>
     */
    public static function for(User $user): array
    {
        $items = [];

        foreach (self::items($user->role) as $item) {
            if (! Route::has($item['route'])) {
                continue; // halaman belum dibuat
            }

            $items[] = $item + [
                'url' => route($item['route']),
                // routeIs() menerima pola, mis. 'tickets.*' untuk semua halaman tiket.
                'active' => request()->routeIs(...($item['match'] ?? [$item['route']])),
            ];
        }

        return $items;
    }

    /**
     * Kotak pencarian di top bar: route tujuan + teks petunjuk, atau null kalau halamannya belum ada.
     * Formulirnya mengirim ?q=... ke halaman daftar milik peran itu.
     *
     * @return array{url: string, placeholder: string}|null
     */
    public static function search(User $user): ?array
    {
        [$route, $placeholder] = match ($user->role) {
            Role::EndUser => ['tickets.index', 'Cari nomor tiket…'],
            Role::Staff => ['staff.queue', 'Cari nomor tiket…'],
            Role::Admin => ['admin.queue', 'Cari nomor tiket…'],
            Role::SuperAdmin => ['superadmin.users.index', 'Cari NIM / NIDN / email…'],
        };

        return Route::has($route) ? ['url' => route($route), 'placeholder' => $placeholder] : null;
    }

    /** Teks kartu hijau di bawah sidebar (mockup A), disesuaikan per peran. */
    public static function tip(Role $role): array
    {
        return match ($role) {
            Role::EndUser => ['Lacak SLA tiket Anda', 'Setiap tiket punya target waktu penyelesaian. Pantau statusnya di Tiket Saya.'],
            Role::Staff => ['Kerjakan yang terlama dulu', 'Antrean diurutkan dari tiket terlama. Perhatikan tanda SLA kuning dan merah.'],
            Role::Admin => ['Pantau tiket menganggur', 'Gunakan Ping untuk mengingatkan Staff bila tiket terlalu lama menunggu.'],
            Role::SuperAdmin => ['Satu katalog, enam fakultas', 'Fakultas baru otomatis mendapat Katalog Kategori Layanan Standar.'],
        };
    }

    /**
     * Daftar menu lengkap per peran sesuai PRD 7.5 (logout ada di menu user, bukan di sini).
     * Nama route mengikuti rencana teknis; 'match' = pola route lain yang membuat item ini aktif.
     */
    private static function items(Role $role): array
    {
        return match ($role) {
            Role::EndUser => [
                ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
                ['route' => 'tickets.create', 'label' => 'Buat Tiket', 'icon' => 'circle-plus', 'fab' => true],
                ['route' => 'tickets.index', 'label' => 'Tiket Saya', 'icon' => 'ticket', 'match' => ['tickets.index', 'tickets.show']],
                ['route' => 'history.index', 'label' => 'Riwayat Tiket', 'icon' => 'history'],
            ],
            Role::Staff => [
                ['route' => 'staff.dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
                ['route' => 'staff.queue', 'label' => 'Antrean Tiket', 'icon' => 'inbox'],
                ['route' => 'staff.tickets.index', 'label' => 'Tiket Saya', 'icon' => 'ticket', 'match' => ['staff.tickets.*']],
                ['route' => 'staff.history', 'label' => 'Riwayat Tiket', 'icon' => 'history'],
            ],
            Role::Admin => [
                ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
                ['route' => 'admin.queue', 'label' => 'Monitor Antrean', 'icon' => 'inbox'],
                ['route' => 'admin.staff.index', 'label' => 'Kelola Staff', 'icon' => 'users', 'match' => ['admin.staff.*']],
                ['route' => 'admin.services.index', 'label' => 'Pengaturan Layanan', 'icon' => 'sliders', 'match' => ['admin.services.*']],
                ['route' => 'admin.reports.index', 'label' => 'Laporan', 'icon' => 'chart', 'match' => ['admin.reports.*']],
            ],
            Role::SuperAdmin => [
                ['route' => 'superadmin.dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
                ['route' => 'superadmin.faculties.index', 'label' => 'Fakultas', 'icon' => 'landmark', 'match' => ['superadmin.faculties.*']],
                ['route' => 'superadmin.users.index', 'label' => 'Pengguna', 'icon' => 'users', 'match' => ['superadmin.users.*']],
            ],
        };
    }
}
