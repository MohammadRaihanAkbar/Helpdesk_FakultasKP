<?php

namespace App\View\Components;

use App\Models\Category;
use App\Models\Faculty;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Sisi kiri halaman login (mockup design/a-login.png): judul, kode fakultas, dan 3 angka.
 *
 * Ini komponen BERKELAS (ada file PHP + file Blade), bukan komponen anonim seperti x-ui.*,
 * karena butuh data dari database. Query-nya ditaruh di sini supaya LoginController tetap
 * tipis dan view tidak berisi query. Semua angka asli dari database, bukan angka contoh:
 *   - fakultas terhubung = fakultas aktif
 *   - jenis layanan      = nama kategori leaf aktif yang berbeda (katalog standar: 11)
 *   - SLA tercepat       = target SLA terkecil di antara kategori leaf aktif
 */
class LoginShowcase extends Component
{
    public function render(): View
    {
        $faculties = Faculty::query()->where('is_active', true)->orderBy('id')->pluck('code');

        $leaves = Category::query()
            ->active()
            ->leaves()
            ->whereHas('faculty', fn ($query) => $query->where('is_active', true));

        return view('components.login-showcase', [
            'facultyCodes' => $faculties,
            'serviceCount' => (clone $leaves)->distinct()->count('name'),
            'fastestSla' => (clone $leaves)->min('sla_hours'),
        ]);
    }
}
