<?php

namespace Tests\Feature\View;

use App\Models\Category;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard Staff, Admin Fakultas, dan Super Admin (M1: kosong, tanpa angka contoh).
 */
class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_sees_its_own_dashboard_with_name_and_role(): void
    {
        $faculty = Faculty::factory()->create(['code' => 'FITK', 'name' => 'Fakultas Ilmu Tarbiyah dan Keguruan']);

        $cases = [
            '/dashboard' => [User::factory()->create(['name' => 'Ahmad Mahasiswa']), 'Pelapor'],
            '/staff/dashboard' => [User::factory()->staff($faculty)->create(['name' => 'Sari Staff']), 'Staff Fakultas'],
            '/admin/dashboard' => [User::factory()->admin($faculty)->create(['name' => 'Budi Admin']), 'Admin Fakultas'],
            '/super-admin/dashboard' => [User::factory()->superAdmin()->create(['name' => 'Super Admin IT']), 'Super Admin'],
        ];

        foreach ($cases as $url => [$user, $roleLabel]) {
            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertSeeText('Selamat datang, '.explode(' ', $user->name)[0])
                ->assertSee($roleLabel);
        }
    }

    public function test_staff_dashboard_lists_specialties_and_faculty(): void
    {
        $faculty = Faculty::factory()->create(['name' => 'Fakultas Contoh']);
        $staff = User::factory()->staff($faculty)->create();
        $staff->specialties()->attach(Category::where('faculty_id', $faculty->id)->where('name', 'Keuangan & UKT')->first());

        $this->actingAs($staff)->get('/staff/dashboard')
            ->assertSee('Keuangan &amp; UKT', false)
            ->assertSee('Fakultas Contoh')
            ->assertSee('Antrean masih kosong');
    }

    public function test_staff_without_specialty_is_told_to_contact_admin(): void
    {
        $this->actingAs(User::factory()->staff()->create())->get('/staff/dashboard')
            ->assertSee('Belum ada spesialisasi. Hubungi Admin Fakultas.');
    }

    public function test_greeting_skips_academic_titles(): void
    {
        $this->actingAs(User::factory()->dosen()->create(['name' => 'Dr. Budi Dosen']))->get('/dashboard')
            ->assertSeeText('Selamat datang, Budi');
    }
}
