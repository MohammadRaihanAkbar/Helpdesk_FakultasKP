<?php

namespace Tests\Feature\Admin;

use App\Enums\UserType;
use App\Models\Category;
use App\Models\Faculty;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengaturan Layanan Admin Fakultas (US-11, PRD 2.7 aturan 1–11, F-ADM-04, F-SYS-04).
 */
class ServiceSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Faculty $faculty;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Factory fakultas otomatis menyalin 12 kategori standar (event Faculty::created).
        $this->faculty = Faculty::factory()->create(['code' => 'FITK', 'name' => 'Fakultas Ilmu Tarbiyah dan Keguruan']);
        $this->admin = User::factory()->admin($this->faculty)->create();
    }

    private function standard(string $name, ?Faculty $faculty = null): Category
    {
        return Category::where('faculty_id', ($faculty ?? $this->faculty)->id)->where('name', $name)->firstOrFail();
    }

    private function extraGroup(array $attributes = [], array $audiences = ['mahasiswa']): Category
    {
        $group = Category::factory()->create(['faculty_id' => $this->faculty->id] + $attributes);
        foreach ($audiences as $type) {
            $group->audiences()->create(['user_type' => $type]);
        }

        return $group;
    }

    // ---------------------------------------------------------------
    // Halaman pohon kategori
    // ---------------------------------------------------------------

    public function test_page_shows_two_level_tree_with_labels_and_audiences(): void
    {
        $this->extraGroup(['name' => 'Laboratorium Microteaching', 'sla_hours' => 48], ['mahasiswa', 'dosen']);

        $this->actingAs($this->admin)->get('/admin/services')
            ->assertOk()
            ->assertSee('Pengaturan Layanan — FITK')
            ->assertSeeInOrder(['Sistem Akademik &amp; Perkuliahan', 'Nilai belum muncul', 'KRS belum di-ACC', 'Jadwal mata kuliah'], false)
            ->assertSee('Standar')
            ->assertSee('Tambahan')
            ->assertSee('Laboratorium Microteaching')
            ->assertSee('Sensitif')       // Integritas Dosen
            ->assertSee('Mahasiswa')
            ->assertSee('Dosen')
            ->assertSee('Alumni')
            ->assertSee('Kategori Tambahan');
    }

    public function test_page_only_lists_categories_of_the_admins_faculty(): void
    {
        $other = Faculty::factory()->create();
        Category::factory()->create(['faculty_id' => $other->id, 'name' => 'Studio Penyiaran']);

        $this->actingAs($this->admin)->get('/admin/services')->assertOk()->assertDontSee('Studio Penyiaran');
    }

    public function test_standard_categories_have_no_delete_button_but_extra_ones_do(): void
    {
        $extra = $this->extraGroup(['name' => 'Lab Bahasa']);
        $standard = $this->standard('Keuangan & UKT');

        $html = $this->actingAs($this->admin)->get('/admin/services')->getContent();

        $this->assertStringContainsString('action="'.route('admin.services.destroy', $extra).'"', $html);
        $this->assertStringNotContainsString('action="'.route('admin.services.destroy', $standard).'"', $html);
    }

    public function test_other_roles_cannot_open_service_settings(): void
    {
        foreach ([User::factory()->staff($this->faculty)->create(), User::factory()->create(), User::factory()->superAdmin()->create()] as $user) {
            $this->actingAs($user)->get('/admin/services')->assertForbidden();
        }
    }

    public function test_admin_without_faculty_gets_403(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/services')->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Kategori standar
    // ---------------------------------------------------------------

    public function test_standard_category_sla_description_and_active_can_be_changed(): void
    {
        $krs = $this->standard('KRS belum di-ACC');

        $this->actingAs($this->admin)->put("/admin/services/{$krs->id}", [
            'sla_hours' => 12, 'description' => 'Mendesak saat masa KRS', 'is_active' => '0',
        ])->assertRedirect('/admin/services')->assertSessionHasNoErrors();

        $krs->refresh();
        $this->assertSame(12, $krs->sla_hours);
        $this->assertSame('Mendesak saat masa KRS', $krs->description);
        $this->assertFalse($krs->is_active);
        $this->assertTrue($krs->is_standard);
        $this->assertSame('KRS belum di-ACC', $krs->name);
    }

    public function test_standard_category_name_cannot_be_changed(): void
    {
        $ukt = $this->standard('Keuangan & UKT');

        $this->actingAs($this->admin)->from('/admin/services')->put("/admin/services/{$ukt->id}", [
            'name' => 'Keuangan', 'sla_hours' => 72,
        ])->assertSessionHasErrors('rule');

        $this->assertSame('Keuangan & UKT', $ukt->fresh()->name);
    }

    public function test_standard_category_parent_and_audiences_are_locked_json(): void
    {
        $ukt = $this->standard('Keuangan & UKT');

        $this->actingAs($this->admin)->putJson("/admin/services/{$ukt->id}", ['sla_hours' => 72, 'audiences' => ['dosen']])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'STANDARD_CATEGORY_LOCKED');

        $this->assertSame(['mahasiswa'], $ukt->audiences()->pluck('user_type')->map->value->all());
    }

    public function test_sending_unchanged_locked_fields_is_allowed(): void
    {
        $krs = $this->standard('KRS belum di-ACC');

        $this->actingAs($this->admin)->putJson("/admin/services/{$krs->id}", [
            'name' => 'KRS belum di-ACC', 'parent_id' => $krs->parent_id, 'sla_hours' => 30,
        ])->assertOk();

        $this->assertSame(30, $krs->fresh()->sla_hours);
    }

    public function test_partial_update_does_not_reactivate_category(): void
    {
        $ukt = $this->standard('Keuangan & UKT');
        $ukt->update(['is_active' => false]);

        $this->actingAs($this->admin)->putJson("/admin/services/{$ukt->id}", ['sla_hours' => 48])->assertOk();

        $this->assertFalse($ukt->fresh()->is_active);
    }

    public function test_standard_category_cannot_be_deleted_web(): void
    {
        $ukt = $this->standard('Keuangan & UKT');

        $this->actingAs($this->admin)->from('/admin/services')->delete("/admin/services/{$ukt->id}")
            ->assertRedirect('/admin/services')
            ->assertSessionHasErrors(['rule' => 'Kategori standar tidak dapat dihapus. Nonaktifkan kategori jika tidak digunakan di fakultas Anda.']);

        $this->assertModelExists($ukt);
    }

    public function test_standard_category_cannot_be_deleted_json_returns_409(): void
    {
        $ukt = $this->standard('Keuangan & UKT');

        $this->actingAs($this->admin)->deleteJson("/admin/services/{$ukt->id}")
            ->assertStatus(409)
            ->assertJson(['success' => false, 'error' => ['code' => 'STANDARD_CATEGORY_LOCKED']]);
    }

    public function test_sla_must_be_a_positive_number_of_hours(): void
    {
        $ukt = $this->standard('Keuangan & UKT');

        foreach (['0', '-5', 'abc', '1000', ''] as $value) {
            $this->actingAs($this->admin)->put("/admin/services/{$ukt->id}", ['sla_hours' => $value])
                ->assertSessionHasErrors('sla_hours');
        }

        $this->assertSame(72, $ukt->fresh()->sla_hours);
    }

    public function test_sla_change_only_affects_new_tickets(): void
    {
        $ukt = $this->standard('Keuangan & UKT');
        $oldTicket = Ticket::factory()->for($ukt)->create();

        $this->actingAs($this->admin)->put("/admin/services/{$ukt->id}", ['sla_hours' => 24]);

        $this->assertSame(72, $oldTicket->fresh()->sla_target_hours);
    }

    // ---------------------------------------------------------------
    // Kategori tambahan: membuat
    // ---------------------------------------------------------------

    public function test_admin_can_create_extra_group_with_audiences(): void
    {
        $this->actingAs($this->admin)->post('/admin/services', [
            'name' => 'Laboratorium Microteaching', 'description' => 'Peminjaman lab',
            'audiences' => ['mahasiswa', 'dosen'], 'sla_hours' => 48, 'is_active' => '1',
        ])->assertRedirect('/admin/services')->assertSessionHasNoErrors();

        $group = Category::where('name', 'Laboratorium Microteaching')->firstOrFail();
        $this->assertSame($this->faculty->id, $group->faculty_id);
        $this->assertFalse($group->is_standard);
        $this->assertNull($group->parent_id);
        $this->assertSame(48, $group->sla_hours);
        $this->assertEqualsCanonicalizing(['mahasiswa', 'dosen'], $group->audiences()->pluck('user_type')->map->value->all());
    }

    public function test_faculty_id_from_input_is_ignored(): void
    {
        $other = Faculty::factory()->create();

        $this->actingAs($this->admin)->post('/admin/services', [
            'name' => 'Lab Komputer', 'audiences' => ['mahasiswa'], 'sla_hours' => 24, 'faculty_id' => $other->id, 'is_standard' => '1',
        ]);

        $category = Category::where('name', 'Lab Komputer')->firstOrFail();
        $this->assertSame($this->faculty->id, $category->faculty_id);
        $this->assertFalse($category->is_standard);
    }

    public function test_extra_group_requires_audiences(): void
    {
        $this->actingAs($this->admin)->post('/admin/services', ['name' => 'Lab Komputer', 'sla_hours' => 24])
            ->assertSessionHasErrors('audiences');

        $this->actingAs($this->admin)->post('/admin/services', ['name' => 'Lab Komputer', 'sla_hours' => 24, 'audiences' => ['dekan']])
            ->assertSessionHasErrors('audiences.0');

        $this->assertDatabaseMissing('categories', ['name' => 'Lab Komputer']);
    }

    public function test_extra_child_under_standard_group_that_has_children(): void
    {
        $akademik = $this->standard('Sistem Akademik & Perkuliahan');

        $this->actingAs($this->admin)->post('/admin/services', [
            'name' => 'Cuti akademik', 'parent_id' => $akademik->id, 'audiences' => ['alumni'],
        ])->assertSessionHasNoErrors();

        $child = Category::where('name', 'Cuti akademik')->firstOrFail();
        $this->assertSame($akademik->id, $child->parent_id);
        $this->assertSame($akademik->sla_hours, $child->sla_hours);   // SLA default dari induk (72)
        $this->assertSame(0, $child->audiences()->count());           // mewarisi audiens induk
        $this->assertSame([UserType::Mahasiswa], $child->audienceTypes());
    }

    public function test_child_sla_defaults_to_parent_sla_but_can_be_overridden(): void
    {
        $group = $this->extraGroup(['name' => 'Lab Sains', 'sla_hours' => 36]);

        $this->actingAs($this->admin)->post('/admin/services', ['name' => 'Alat rusak', 'parent_id' => $group->id]);
        $this->actingAs($this->admin)->post('/admin/services', ['name' => 'Peminjaman alat', 'parent_id' => $group->id, 'sla_hours' => 8]);

        $this->assertSame(36, Category::where('name', 'Alat rusak')->value('sla_hours'));
        $this->assertSame(8, Category::where('name', 'Peminjaman alat')->value('sla_hours'));
    }

    public function test_create_form_prefills_sla_from_parent(): void
    {
        $group = $this->extraGroup(['name' => 'Lab Sains', 'sla_hours' => 36]);

        $this->actingAs($this->admin)->get('/admin/services/create?parent_id='.$group->id)
            ->assertOk()
            ->assertSee('Tambah Kategori Tambahan')
            ->assertSee("sla: '36'", false);
    }

    public function test_standard_group_without_children_cannot_get_children(): void
    {
        $ukt = $this->standard('Keuangan & UKT');

        $this->actingAs($this->admin)->post('/admin/services', ['name' => 'Cicilan UKT', 'parent_id' => $ukt->id])
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($this->admin)->postJson('/admin/services', ['name' => 'Cicilan UKT', 'parent_id' => $ukt->id])
            ->assertStatus(422)->assertJsonPath('error.code', 'INVALID_PARENT');

        $this->assertDatabaseMissing('categories', ['name' => 'Cicilan UKT']);
    }

    public function test_hierarchy_is_limited_to_two_levels(): void
    {
        $krs = $this->standard('KRS belum di-ACC'); // Jenis Masalah

        $this->actingAs($this->admin)->post('/admin/services', ['name' => 'Tingkat tiga', 'parent_id' => $krs->id, 'sla_hours' => 24])
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('categories', ['name' => 'Tingkat tiga']);
    }

    public function test_parent_from_another_faculty_is_rejected(): void
    {
        $other = Faculty::factory()->create();
        $foreignGroup = $this->standard('Sistem Akademik & Perkuliahan', $other);

        $this->actingAs($this->admin)->post('/admin/services', ['name' => 'Titipan', 'parent_id' => $foreignGroup->id])
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('categories', ['name' => 'Titipan']);
    }

    public function test_name_must_be_unique_within_faculty_including_standard_names(): void
    {
        $this->actingAs($this->admin)->post('/admin/services', ['name' => 'Keuangan & UKT', 'audiences' => ['mahasiswa'], 'sla_hours' => 24])
            ->assertSessionHasErrors('name');

        // Nama yang sama di fakultas LAIN boleh.
        $other = Faculty::factory()->create();
        Category::factory()->create(['faculty_id' => $other->id, 'name' => 'Lab Bahasa']);

        $this->actingAs($this->admin)->post('/admin/services', ['name' => 'Lab Bahasa', 'audiences' => ['mahasiswa'], 'sla_hours' => 24])
            ->assertSessionHasNoErrors();
    }

    // ---------------------------------------------------------------
    // Kategori tambahan: mengubah
    // ---------------------------------------------------------------

    public function test_extra_category_can_be_fully_edited(): void
    {
        $group = $this->extraGroup(['name' => 'Lab Bahasa', 'sla_hours' => 48], ['mahasiswa']);

        $this->actingAs($this->admin)->put("/admin/services/{$group->id}", [
            'name' => 'Laboratorium Bahasa', 'audiences' => ['dosen', 'alumni'], 'sla_hours' => 24, 'description' => 'Baru', 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $group->refresh();
        $this->assertSame('Laboratorium Bahasa', $group->name);
        $this->assertSame(24, $group->sla_hours);
        $this->assertEqualsCanonicalizing(['dosen', 'alumni'], $group->audiences()->pluck('user_type')->map->value->all());
    }

    public function test_extra_group_with_children_cannot_become_a_child(): void
    {
        $group = $this->extraGroup(['name' => 'Lab Sains']);
        Category::factory()->childOf($group)->create();
        $target = $this->extraGroup(['name' => 'Lab Lain']);

        $this->actingAs($this->admin)->put("/admin/services/{$group->id}", ['name' => 'Lab Sains', 'parent_id' => $target->id])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($group->fresh()->parent_id);
    }

    public function test_edit_page_for_standard_category_shows_locked_fields(): void
    {
        $ukt = $this->standard('Keuangan & UKT');

        $this->actingAs($this->admin)->get("/admin/services/{$ukt->id}/edit")
            ->assertOk()
            ->assertSee('Kategori standar terkunci')
            ->assertDontSee('name="name"', false)
            ->assertSee('name="sla_hours"', false);
    }

    // ---------------------------------------------------------------
    // Kategori tambahan: menghapus
    // ---------------------------------------------------------------

    public function test_unused_extra_category_can_be_deleted_with_its_children(): void
    {
        $group = $this->extraGroup(['name' => 'Lab Sains']);
        $child = Category::factory()->childOf($group)->create();
        $staff = User::factory()->staff($this->faculty)->create();
        $staff->specialties()->attach($group);

        $this->actingAs($this->admin)->delete("/admin/services/{$group->id}")->assertRedirect('/admin/services');

        $this->assertModelMissing($group);
        $this->assertModelMissing($child);
        $this->assertSame(0, $staff->specialties()->count());
    }

    public function test_extra_category_used_by_a_ticket_cannot_be_deleted(): void
    {
        $group = $this->extraGroup(['name' => 'Lab Sains']);
        Ticket::factory()->for($group)->create();

        $this->actingAs($this->admin)->deleteJson("/admin/services/{$group->id}")
            ->assertStatus(409)->assertJsonPath('error.code', 'CATEGORY_IN_USE');

        $this->assertModelExists($group);
    }

    public function test_group_whose_child_was_used_cannot_be_deleted(): void
    {
        $group = $this->extraGroup(['name' => 'Lab Sains']);
        $child = Category::factory()->childOf($group)->create();
        Ticket::factory()->for($child)->create();

        $this->actingAs($this->admin)->from('/admin/services')->delete("/admin/services/{$group->id}")
            ->assertSessionHasErrors('rule');

        $this->assertModelExists($group);
        $this->assertModelExists($child);
    }

    // ---------------------------------------------------------------
    // On/Off
    // ---------------------------------------------------------------

    public function test_turning_a_group_off_hides_its_children_and_on_restores_them(): void
    {
        $akademik = $this->standard('Sistem Akademik & Perkuliahan');
        $krs = $this->standard('KRS belum di-ACC');

        $this->actingAs($this->admin)->patch("/admin/services/{$akademik->id}/status", ['is_active' => '0'])->assertRedirect();

        $this->assertFalse($akademik->fresh()->is_active);
        $this->assertFalse($krs->fresh()->isEffectivelyActive());
        $this->assertFalse($krs->fresh()->isSelectable());
        $this->assertTrue($krs->fresh()->is_active); // status milik anak sendiri tidak ditimpa

        $this->actingAs($this->admin)->get('/admin/services')->assertSee('Nonaktif (ikut kelompok)');

        $this->actingAs($this->admin)->patch("/admin/services/{$akademik->id}/status", ['is_active' => '1']);
        $this->assertTrue($krs->fresh()->isSelectable());
    }

    public function test_deactivate_button_uses_a_confirmation_dialog(): void
    {
        $this->actingAs($this->admin)->get('/admin/services')
            ->assertSee('<dialog', false)
            ->assertSee('Ya, nonaktifkan')
            ->assertSee('ikut tersembunyi');
    }

    // ---------------------------------------------------------------
    // Lintas fakultas
    // ---------------------------------------------------------------

    public function test_admin_cannot_touch_categories_of_another_faculty(): void
    {
        $other = Faculty::factory()->create();
        $foreignStandard = $this->standard('Keuangan & UKT', $other);
        $foreignExtra = Category::factory()->create(['faculty_id' => $other->id]);

        $this->actingAs($this->admin)->get("/admin/services/{$foreignStandard->id}/edit")->assertForbidden();
        $this->actingAs($this->admin)->put("/admin/services/{$foreignStandard->id}", ['sla_hours' => 1])->assertForbidden();
        $this->actingAs($this->admin)->patch("/admin/services/{$foreignStandard->id}/status", ['is_active' => '0'])->assertForbidden();
        $this->actingAs($this->admin)->delete("/admin/services/{$foreignExtra->id}")->assertForbidden();

        $this->assertSame(72, $foreignStandard->fresh()->sla_hours);
        $this->assertTrue($foreignStandard->fresh()->is_active);
        $this->assertModelExists($foreignExtra);
    }
}
