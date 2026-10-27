<?php

namespace Tests\Feature\Admin;

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Faculty;
use App\Models\Ticket;
use App\Models\User;
use App\Services\StaffSpecialtyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spesialisasi Staff (US-13, F-ADM-03) + ringkasan cakupan "Kelompok tanpa Staff".
 */
class StaffSpecialtyTest extends TestCase
{
    use RefreshDatabase;

    private Faculty $faculty;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faculty = Faculty::factory()->create(['code' => 'FITK']);
        $this->admin = User::factory()->admin($this->faculty)->create();
        $this->staff = User::factory()->staff($this->faculty)->create(['name' => 'Rina Staff']);
    }

    private function cat(string $name, ?Faculty $faculty = null): Category
    {
        return Category::where('faculty_id', ($faculty ?? $this->faculty)->id)->where('name', $name)->firstOrFail();
    }

    public function test_page_shows_active_groups_grouped_by_audience(): void
    {
        $extra = Category::factory()->create(['faculty_id' => $this->faculty->id, 'name' => 'Laboratorium Microteaching']);
        $extra->audiences()->create(['user_type' => 'mahasiswa']);

        $this->actingAs($this->admin)->get("/admin/staff/{$this->staff->id}/specialties")
            ->assertOk()
            ->assertSee('Spesialisasi: Rina Staff')
            ->assertSeeInOrder([
                'Mahasiswa &amp; Dosen (bersama)', 'Pelayanan Administrasi',
                'Khusus Mahasiswa', 'Sistem Akademik',
                'Khusus Dosen', 'Jadwal Mengajar',
                'Khusus Alumni', 'Transkrip Nilai',
                'Kategori Tambahan FITK', 'Laboratorium Microteaching',
            ], false)
            // Jenis Masalah hanya keterangan, bukan checkbox
            ->assertSee('(Nilai belum muncul, KRS belum di-ACC, Jadwal mata kuliah)')
            ->assertDontSee('value="'.$this->cat('KRS belum di-ACC')->id.'"', false);
    }

    public function test_inactive_groups_are_not_offered(): void
    {
        $ukt = $this->cat('Keuangan & UKT');
        $ukt->update(['is_active' => false]);

        $this->actingAs($this->admin)->get("/admin/staff/{$this->staff->id}/specialties")
            ->assertDontSee('value="'.$ukt->id.'"', false);
    }

    public function test_integritas_dosen_is_labelled_sensitive_with_warning(): void
    {
        $this->actingAs($this->admin)->get("/admin/staff/{$this->staff->id}/specialties")
            ->assertSee('Sensitif')
            ->assertSee('Kategori sensitif: petakan hanya ke staf yang ditunjuk (mis. Dekanat/Penjaminan Mutu).');
    }

    public function test_admin_saves_specialties_and_queue_follows_immediately(): void
    {
        $akademik = $this->cat('Sistem Akademik & Perkuliahan');
        $it = $this->cat('Akun, Jaringan & IT');
        $ticket = Ticket::factory()->for($this->cat('KRS belum di-ACC'))->create();

        $this->assertSame(0, Ticket::forStaff($this->staff)->count());

        $this->actingAs($this->admin)->put("/admin/staff/{$this->staff->id}/specialties", ['category_ids' => [$akademik->id, $it->id]])
            ->assertRedirect('/admin/staff')->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([$akademik->id, $it->id], $this->staff->specialties()->pluck('categories.id')->all());
        $this->assertTrue(Ticket::forStaff($this->staff)->first()->is($ticket));

        // Hapus semua centang -> staff tanpa spesialisasi (field tidak terkirim sama sekali)
        $this->actingAs($this->admin)->put("/admin/staff/{$this->staff->id}/specialties", [])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->staff->specialties()->count());
        $this->assertSame(0, Ticket::forStaff($this->staff)->count());
    }

    public function test_only_active_top_level_groups_of_own_faculty_are_accepted(): void
    {
        $invalid = [
            'Jenis Masalah' => $this->cat('KRS belum di-ACC')->id,
            'fakultas lain' => $this->cat('Keuangan & UKT', Faculty::factory()->create())->id,
            'nonaktif' => tap($this->cat('Keuangan & UKT'))->update(['is_active' => false])->id,
            'tidak ada' => 999999,
        ];

        foreach ($invalid as $case => $id) {
            $this->actingAs($this->admin)->putJson("/admin/staff/{$this->staff->id}/specialties", ['category_ids' => [$id]])
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'INVALID_SPECIALTY');
        }

        $this->actingAs($this->admin)->from("/admin/staff/{$this->staff->id}/specialties")
            ->put("/admin/staff/{$this->staff->id}/specialties", ['category_ids' => [$invalid['Jenis Masalah']]])
            ->assertSessionHasErrors('category_ids');

        $this->assertSame(0, $this->staff->specialties()->count());
    }

    public function test_mapping_to_inactive_group_is_kept_when_saving(): void
    {
        $ukt = $this->cat('Keuangan & UKT');
        $this->staff->specialties()->attach($ukt);
        $ukt->update(['is_active' => false]);

        $it = $this->cat('Akun, Jaringan & IT');
        $this->actingAs($this->admin)->put("/admin/staff/{$this->staff->id}/specialties", ['category_ids' => [$it->id]]);

        $this->assertEqualsCanonicalizing([$ukt->id, $it->id], $this->staff->specialties()->pluck('categories.id')->all());
    }

    public function test_coverage_lists_groups_without_active_staff(): void
    {
        $ukt = $this->cat('Keuangan & UKT');
        $it = $this->cat('Akun, Jaringan & IT');
        $this->staff->specialties()->attach($ukt);
        User::factory()->staff($this->faculty)->inactive()->create()->specialties()->attach($it); // nonaktif: tidak dihitung
        Ticket::factory()->for($it)->count(2)->create();
        Ticket::factory()->for($it)->create(['status' => TicketStatus::Diproses]); // bukan "menunggu"

        $coverage = app(StaffSpecialtyService::class)->coverage($this->faculty->id)->keyBy(fn ($row) => $row['group']->name);

        $this->assertSame(1, $coverage['Keuangan & UKT']['staff_count']);
        $this->assertSame(0, $coverage['Akun, Jaringan & IT']['staff_count']);
        $this->assertSame(2, $coverage['Akun, Jaringan & IT']['waiting_count']);
        $this->assertCount(9, $coverage); // 9 Kelompok Layanan standar, semuanya aktif

        $this->actingAs($this->admin)->get("/admin/staff/{$this->staff->id}/specialties")
            ->assertSee('Kelompok tanpa Staff (8)')
            ->assertSee('2 tiket menunggu');
    }

    public function test_admin_dashboard_links_to_staff_and_services_and_shows_coverage(): void
    {
        $this->actingAs($this->admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(route('admin.staff.index'))
            ->assertSee(route('admin.services.index'))
            ->assertSee('Kelompok tanpa Staff (9)');
    }
}
