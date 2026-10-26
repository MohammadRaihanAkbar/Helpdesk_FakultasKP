<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Category;
use App\Models\Faculty;
use App\Models\Ticket;

/**
 * Master Fakultas (US-17, F-SPA-01): daftar + statistik, tambah (katalog standar tersalin), ubah, aktif/nonaktif.
 */
class FacultyManagementTest extends SuperAdminTestCase
{
    private function facultyPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'FASTIH',
            'name' => 'Fakultas Sains, Teknologi, dan Ilmu Kesehatan',
            'slug' => '',
            'description' => 'Fakultas baru hasil pemekaran.',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_only_super_admin_can_open_and_change_faculties(): void
    {
        foreach ($this->nonSuperAdmins() as $user) {
            $this->actingAs($user)->get('/super-admin/faculties')->assertForbidden();
            $this->actingAs($user)->get('/super-admin/faculties/create')->assertForbidden();
            $this->actingAs($user)->post('/super-admin/faculties', $this->facultyPayload())->assertForbidden();
            $this->actingAs($user)->put("/super-admin/faculties/{$this->febi->id}", $this->facultyPayload(['code' => 'FEBI']))->assertForbidden();
            $this->actingAs($user)->patch("/super-admin/faculties/{$this->febi->id}/status", ['is_active' => 0])->assertForbidden();
        }

        $this->assertDatabaseMissing('faculties', ['code' => 'FASTIH']);
        $this->assertTrue($this->febi->fresh()->is_active);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/super-admin/faculties')->assertRedirect('/login');
    }

    public function test_list_shows_short_statistics_per_faculty(): void
    {
        $this->ticket();
        $this->ticket(['category' => $this->cat('Fasilitas, Sarana & Prasarana')]);

        $response = $this->actingAs($this->superAdmin)->get('/super-admin/faculties')
            ->assertOk()
            ->assertSee('Fakultas Ilmu Tarbiyah dan Keguruan')
            ->assertSee('Kelompok tanpa Staff');

        $fitk = $response->viewData('faculties')->firstWhere('id', $this->fitk->id);
        $this->assertSame(1, $fitk->admins_count);
        $this->assertSame(5, $fitk->active_staff_count);   // akademik, akademik2, umum, dekanat, tanpaSpesialisasi
        $this->assertSame(6, $fitk->staff_count);          // + 1 nonaktif
        $this->assertSame(2, $fitk->tickets_count);
        // 9 kelompok standar, 3 dipegang staff aktif (Sistem Akademik, Fasilitas, Integritas Dosen)
        $this->assertSame(6, $fitk->uncovered_count);

        $febi = $response->viewData('faculties')->firstWhere('id', $this->febi->id);
        $this->assertSame([1, 1, 0, 8], [$febi->admins_count, $febi->active_staff_count, $febi->tickets_count, $febi->uncovered_count]);
    }

    public function test_new_faculty_gets_the_standard_catalog_from_the_form(): void
    {
        $this->actingAs($this->superAdmin)
            ->post('/super-admin/faculties', $this->facultyPayload(['code' => ' fastih ']))
            ->assertRedirect('/super-admin/faculties')
            ->assertSessionHas('status');

        $faculty = Faculty::where('code', 'FASTIH')->firstOrFail(); // disimpan huruf besar
        $this->assertSame('fastih', $faculty->slug);                // slug kosong -> dari kode
        $this->assertTrue($faculty->is_active);

        // Katalog standar tersalin persis seperti fakultas lain (jumlah, standar, audiens, SLA).
        $this->assertSame(Category::where('faculty_id', $this->fitk->id)->count(), $faculty->categories()->count());
        $this->assertSame(0, $faculty->categories()->where('is_standard', false)->count());
        $krs = $faculty->categories()->where('name', 'KRS belum di-ACC')->firstOrFail();
        $this->assertSame(24, $krs->sla_hours);
        $this->assertSame(['mahasiswa'], collect($krs->audienceTypes())->map->value->all());

        // Langsung muncul di form buat tiket pelapor.
        $this->actingAs($this->mahasiswa)->get('/tickets/new')->assertOk()->assertSee('Fakultas Sains, Teknologi, dan Ilmu Kesehatan');
    }

    public function test_faculty_code_must_be_unique(): void
    {
        $this->actingAs($this->superAdmin)
            ->from('/super-admin/faculties/create')
            ->post('/super-admin/faculties', $this->facultyPayload(['code' => 'fitk', 'slug' => 'fitk-baru']))
            ->assertRedirect('/super-admin/faculties/create')
            ->assertSessionHasErrors(['code' => 'Kode fakultas ini sudah dipakai fakultas lain.']);

        $this->actingAs($this->superAdmin)
            ->post('/super-admin/faculties', $this->facultyPayload(['code' => 'FBARU', 'slug' => $this->febi->slug]))
            ->assertSessionHasErrors('slug');

        $this->assertSame(2, Faculty::count());
    }

    public function test_code_with_spaces_or_symbols_is_rejected(): void
    {
        $this->actingAs($this->superAdmin)
            ->post('/super-admin/faculties', $this->facultyPayload(['code' => 'F I-K']))
            ->assertSessionHasErrors('code');
    }

    public function test_edit_updates_the_faculty_and_keeps_its_own_code(): void
    {
        $this->actingAs($this->superAdmin)->get("/super-admin/faculties/{$this->fitk->id}/edit")
            ->assertOk()->assertSee('Ubah Fakultas FITK');

        $this->actingAs($this->superAdmin)
            ->put("/super-admin/faculties/{$this->fitk->id}", [
                'code' => 'FITK', 'name' => 'Fakultas Ilmu Tarbiyah & Keguruan', 'slug' => $this->fitk->slug,
                'description' => 'Diperbarui', 'is_active' => '1',
            ])
            ->assertRedirect('/super-admin/faculties');

        $this->fitk->refresh();
        $this->assertSame('Fakultas Ilmu Tarbiyah & Keguruan', $this->fitk->name);
        $this->assertSame('Diperbarui', $this->fitk->description);
        // Ubah data tidak menyalin katalog lagi (hook hanya untuk fakultas BARU).
        $this->assertSame(Category::where('faculty_id', $this->febi->id)->count(), $this->fitk->categories()->where('is_standard', true)->count());
    }

    public function test_deactivated_faculty_disappears_from_the_ticket_form(): void
    {
        $this->actingAs($this->superAdmin)
            ->patch("/super-admin/faculties/{$this->febi->id}/status", ['is_active' => 0])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertFalse($this->febi->fresh()->is_active);

        $this->actingAs($this->mahasiswa)->get('/tickets/new')
            ->assertOk()
            ->assertSee('Fakultas Ilmu Tarbiyah dan Keguruan')
            ->assertDontSee('Fakultas Ekonomi dan Bisnis Islam');

        // Kiriman langsung ke fakultas nonaktif juga ditolak.
        $this->actingAs($this->mahasiswa)
            ->post('/tickets', $this->febiTicketPayload())
            ->assertSessionHasErrors('category_id');
        $this->assertSame(0, Ticket::count());

        // Diaktifkan lagi -> muncul lagi.
        $this->actingAs($this->superAdmin)->patch("/super-admin/faculties/{$this->febi->id}/status", ['is_active' => 1]);
        $this->actingAs($this->mahasiswa)->get('/tickets/new')->assertSee('Fakultas Ekonomi dan Bisnis Islam');
    }

    public function test_faculty_menu_appears_for_super_admin(): void
    {
        $this->actingAs($this->superAdmin)->get('/super-admin/dashboard')
            ->assertOk()
            ->assertSee(route('superadmin.faculties.index'))
            ->assertSee(route('superadmin.users.index'))
            ->assertSee('Cari NIM / NIP / email');
    }

    private function febiTicketPayload(): array
    {
        return [
            'faculty_id' => $this->febi->id,
            'category_id' => $this->cat('KRS belum di-ACC', $this->febi)->id,
            'title' => 'KRS belum di-ACC',
            'description' => 'KRS saya belum disetujui Dosen PA.',
        ];
    }
}
