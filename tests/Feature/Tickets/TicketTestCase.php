<?php

namespace Tests\Feature\Tickets;

use App\Models\Category;
use App\Models\Faculty;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bekal bersama untuk tes tiket: fakultas FITK & FEBI (masing-masing otomatis punya 12 kategori standar),
 * seorang mahasiswa FITK, disk privat palsu, dan pembuat berkas uji.
 */
abstract class TicketTestCase extends TestCase
{
    use RefreshDatabase;

    protected Faculty $fitk;

    protected Faculty $febi;

    protected User $mahasiswa;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local'); // berkas uji tidak menyentuh storage/app/private sungguhan

        $this->fitk = Faculty::factory()->create(['code' => 'FITK', 'name' => 'Fakultas Ilmu Tarbiyah dan Keguruan']);
        $this->febi = Faculty::factory()->create(['code' => 'FEBI', 'name' => 'Fakultas Ekonomi dan Bisnis Islam']);
        $this->mahasiswa = User::factory()->mahasiswa($this->fitk)->create(['name' => 'Ahmad Mahasiswa']);
    }

    protected function cat(string $name, ?Faculty $faculty = null): Category
    {
        return Category::where('faculty_id', ($faculty ?? $this->fitk)->id)->where('name', $name)->firstOrFail();
    }

    /** Isian form tiket yang valid (mahasiswa -> FITK -> KRS belum di-ACC). */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'faculty_id' => $this->fitk->id,
            'category_id' => $this->cat('KRS belum di-ACC')->id,
            'title' => 'KRS semester ganjil belum di-ACC Dosen PA',
            'description' => 'KRS sudah saya isi sejak 1 September, tetapi belum disetujui Dosen PA.',
        ], $overrides);
    }

    protected function ticketFor(User $reporter, ?Category $category = null, array $attributes = []): Ticket
    {
        return Ticket::factory()->for($category ?? $this->cat('KRS belum di-ACC'))->create(array_merge([
            'reporter_id' => $reporter->id,
            'reporter_type' => $reporter->user_type,
        ], $attributes));
    }

    /**
     * Berkas "sungguhan" dengan isi tertentu. Dipakai untuk menguji pengecekan ISI file (MIME dari konten),
     * karena UploadedFile::fake()->create() menebak MIME dari nama file saja.
     */
    protected function realFile(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'uji');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    protected function pdf(string $name = 'surat.pdf'): UploadedFile
    {
        return $this->realFile($name, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
    }
}
