<?php

namespace Tests\Feature\Models;

use App\Enums\UserType;
use App\Models\Category;
use App\Models\Faculty;
use App\Services\CategoryCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Katalog standar, scope visibleTo(), dan aturan leaf (PRD 2.7).
 */
class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private Faculty $faculty;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faculty = Faculty::factory()->create();
    }

    private function category(string $name): Category
    {
        return $this->faculty->categories()->where('name', $name)->firstOrFail();
    }

    public function test_new_faculty_automatically_gets_the_standard_catalog(): void
    {
        $categories = $this->faculty->categories;

        $this->assertCount(12, $categories);
        $this->assertSame(9, Category::where('faculty_id', $this->faculty->id)->topLevel()->count());
        $this->assertSame(11, Category::where('faculty_id', $this->faculty->id)->leaves()->count());
        $this->assertTrue($categories->every(fn (Category $c) => $c->is_standard && $c->is_active));
        $this->assertSame(72, $this->category('Nilai belum muncul')->sla_hours);
        $this->assertSame(24, $this->category('KRS belum di-ACC')->sla_hours);
    }

    public function test_seeding_the_catalog_again_does_not_duplicate_or_overwrite(): void
    {
        $this->category('Keuangan & UKT')->update(['sla_hours' => 10]); // diubah Admin Fakultas

        app(CategoryCatalogService::class)->seedForFaculty($this->faculty);

        $this->assertCount(12, $this->faculty->categories()->get());
        $this->assertSame(10, $this->category('Keuangan & UKT')->sla_hours);
    }

    public function test_visible_to_filters_by_audience_and_children_inherit_from_parent(): void
    {
        $visible = fn (UserType $type) => Category::where('faculty_id', $this->faculty->id)
            ->visibleTo($type)->pluck('name')->sort()->values()->all();

        $mahasiswa = $visible(UserType::Mahasiswa);
        $this->assertCount(9, $mahasiswa); // 6 kelompok + 3 jenis masalah (mewarisi audiens induk)
        $this->assertContains('KRS belum di-ACC', $mahasiswa);
        $this->assertContains('Integritas Dosen', $mahasiswa);

        $dosen = $visible(UserType::Dosen);
        $this->assertEqualsCanonicalizing([
            'Akun, Jaringan & IT', 'Fasilitas, Sarana & Prasarana', 'Jadwal Mengajar', 'Pelayanan Administrasi & Birokrasi',
        ], $dosen);
        $this->assertNotContains('Integritas Dosen', $dosen); // dosen TIDAK PERNAH melihat ini

        $this->assertEqualsCanonicalizing(['Kesalahan Data PDDIKTI', 'Transkrip Nilai'], $visible(UserType::Alumni));
    }

    public function test_leaf_rules(): void
    {
        $akademik = $this->category('Sistem Akademik & Perkuliahan');
        $krs = $this->category('KRS belum di-ACC');
        $ukt = $this->category('Keuangan & UKT');

        $this->assertFalse($akademik->isLeaf());  // kelompok yang punya jenis masalah
        $this->assertTrue($krs->isLeaf());        // jenis masalah
        $this->assertTrue($ukt->isLeaf());        // kelompok tanpa jenis masalah

        $this->assertTrue($krs->group()->is($akademik));
        $this->assertTrue($ukt->group()->is($ukt));

        $this->assertTrue($krs->isVisibleTo(UserType::Mahasiswa));
        $this->assertFalse($krs->isVisibleTo(UserType::Dosen));
    }

    public function test_child_is_not_selectable_when_its_group_is_off(): void
    {
        $akademik = $this->category('Sistem Akademik & Perkuliahan');
        $krs = $this->category('KRS belum di-ACC');

        $this->assertTrue($krs->isSelectable());

        $akademik->update(['is_active' => false]);

        $this->assertFalse($krs->fresh()->isSelectable());
        $this->assertFalse($akademik->fresh()->isSelectable());
        $this->assertSame(11, Category::where('faculty_id', $this->faculty->id)->active()->count());
    }

    public function test_is_leaf_uses_eager_loaded_children(): void
    {
        $groups = Category::where('faculty_id', $this->faculty->id)->topLevel()->with('children')->get();

        $this->assertSame(8, $groups->filter->isLeaf()->count());
    }
}
