<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketStatus;
use App\Enums\UserType;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Buat Tiket oleh pelapor (US-03, US-05B, F-TKU-01..03, F-SYS-02, F-SYS-03).
 */
class CreateTicketTest extends TicketTestCase
{
    // ---------------------------------------------------------------
    // Form
    // ---------------------------------------------------------------

    public function test_form_prefills_origin_faculty_and_shows_reporter_type(): void
    {
        $response = $this->actingAs($this->mahasiswa)->get('/tickets/new')
            ->assertOk()
            ->assertSee('Anda melapor sebagai: Mahasiswa')
            ->assertSee('(fakultas asal Anda)');

        $this->assertSame((string) $this->fitk->id, $response->viewData('initial')['faculty']);
    }

    public function test_form_tree_only_contains_active_categories_for_the_reporter_type(): void
    {
        $this->cat('Keuangan & UKT')->update(['is_active' => false]);

        $tree = $this->actingAs($this->mahasiswa)->get('/tickets/new')->viewData('tree');
        $fitkGroups = collect($tree[$this->fitk->id])->pluck('name')->all();

        $this->assertSame([
            'Sistem Akademik & Perkuliahan', 'Pelayanan Administrasi & Birokrasi', 'Integritas Dosen',
            'Fasilitas, Sarana & Prasarana', 'Akun, Jaringan & IT',
        ], $fitkGroups);
        $this->assertSame(['Nilai belum muncul', 'KRS belum di-ACC', 'Jadwal mata kuliah'],
            collect($tree[$this->fitk->id][0]['children'])->pluck('name')->all());
        $this->assertArrayHasKey($this->febi->id, $tree); // semua fakultas aktif ikut di-embed
    }

    public function test_dosen_and_alumni_see_their_own_groups_only(): void
    {
        $dosen = User::factory()->dosen($this->fitk)->create();
        $alumni = User::factory()->alumni(2019, $this->fitk)->create();

        $dosenGroups = collect($this->actingAs($dosen)->get('/tickets/new')->viewData('tree')[$this->fitk->id])->pluck('name')->all();
        $alumniGroups = collect($this->actingAs($alumni)->get('/tickets/new')->viewData('tree')[$this->fitk->id])->pluck('name')->all();

        $this->assertSame(['Pelayanan Administrasi & Birokrasi', 'Fasilitas, Sarana & Prasarana', 'Akun, Jaringan & IT', 'Jadwal Mengajar'], $dosenGroups);
        $this->assertNotContains('Integritas Dosen', $dosenGroups);
        $this->assertSame(['Transkrip Nilai', 'Kesalahan Data PDDIKTI'], $alumniGroups);
    }

    public function test_group_whose_children_are_all_inactive_is_hidden(): void
    {
        $akademik = $this->cat('Sistem Akademik & Perkuliahan');
        $akademik->children()->update(['is_active' => false]);

        $tree = $this->actingAs($this->mahasiswa)->get('/tickets/new')->viewData('tree');

        $this->assertNotContains('Sistem Akademik & Perkuliahan', collect($tree[$this->fitk->id])->pluck('name'));
    }

    public function test_inactive_faculty_is_not_offered(): void
    {
        $this->febi->update(['is_active' => false]);

        $response = $this->actingAs($this->mahasiswa)->get('/tickets/new');

        $this->assertArrayNotHasKey($this->febi->id, $response->viewData('tree'));
        $this->assertFalse($response->viewData('faculties')->contains($this->febi));
    }

    public function test_only_end_users_can_create_tickets(): void
    {
        $staff = User::factory()->staff($this->fitk)->create();
        $admin = User::factory()->admin($this->fitk)->create();

        foreach ([$staff, $admin] as $user) {
            $this->actingAs($user)->get('/tickets/new')->assertForbidden();
            $this->actingAs($user)->post('/tickets', $this->payload())->assertForbidden();
        }
        $this->post('/logout');
        $this->get('/tickets/new')->assertRedirect('/login');
        $this->assertSame(0, Ticket::count());
    }

    // ---------------------------------------------------------------
    // Simpan tiket
    // ---------------------------------------------------------------

    public function test_ticket_is_created_with_number_snapshots_and_waiting_status(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 15, 9));
        $krs = $this->cat('KRS belum di-ACC');

        $response = $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload());

        $ticket = Ticket::firstOrFail();
        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertSame('TKT-202610-0001', $ticket->ticket_number);
        $this->assertSame(TicketStatus::Menunggu, $ticket->status);
        $this->assertSame($this->fitk->id, $ticket->faculty_id);
        $this->assertSame($krs->id, $ticket->category_id);
        $this->assertSame(24, $ticket->sla_target_hours);              // snapshot SLA kategori
        $this->assertSame(UserType::Mahasiswa, $ticket->reporter_type); // snapshot jenis pelapor
        $this->assertSame($this->mahasiswa->id, $ticket->reporter_id);
        $this->assertNull($ticket->handler_id);
        $this->assertNull($ticket->assigned_at);
    }

    public function test_snapshots_do_not_change_when_category_or_user_changes_later(): void
    {
        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload());
        $ticket = Ticket::firstOrFail();

        $this->cat('KRS belum di-ACC')->update(['sla_hours' => 6]);
        $this->mahasiswa->update(['user_type' => UserType::Alumni]);

        $ticket->refresh();
        $this->assertSame(24, $ticket->sla_target_hours);
        $this->assertSame(UserType::Mahasiswa, $ticket->reporter_type);
    }

    public function test_group_without_children_can_be_chosen_directly(): void
    {
        $ukt = $this->cat('Keuangan & UKT');

        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload(['category_id' => $ukt->id]))->assertSessionHasNoErrors();

        $this->assertSame($ukt->id, Ticket::firstOrFail()->category_id);
    }

    public function test_reporter_may_choose_another_faculty(): void
    {
        $febiIt = $this->cat('Akun, Jaringan & IT', $this->febi);

        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload(['faculty_id' => $this->febi->id, 'category_id' => $febiIt->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->febi->id, Ticket::firstOrFail()->faculty_id);
    }

    public function test_reporter_id_and_status_from_input_are_ignored(): void
    {
        $other = User::factory()->mahasiswa()->create();

        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload([
            'reporter_id' => $other->id, 'status' => 'selesai', 'sla_target_hours' => 1, 'handler_id' => $other->id,
        ]));

        $ticket = Ticket::firstOrFail();
        $this->assertSame($this->mahasiswa->id, $ticket->reporter_id);
        $this->assertSame(TicketStatus::Menunggu, $ticket->status);
        $this->assertSame(24, $ticket->sla_target_hours);
        $this->assertNull($ticket->handler_id);
    }

    // ---------------------------------------------------------------
    // INVALID_CATEGORY
    // ---------------------------------------------------------------

    public function test_server_rejects_invalid_categories(): void
    {
        $inactiveLeaf = Category::factory()->create(['faculty_id' => $this->fitk->id, 'is_active' => false]);
        $inactiveLeaf->audiences()->create(['user_type' => 'mahasiswa']);

        $offGroup = $this->cat('Sistem Akademik & Perkuliahan');

        $cases = [
            'kelompok yang punya Jenis Masalah (bukan leaf)' => [$this->fitk->id, $offGroup->id],
            'kategori milik fakultas lain' => [$this->fitk->id, $this->cat('Keuangan & UKT', $this->febi)->id],
            'fakultas & kategori tidak cocok' => [$this->febi->id, $this->cat('Keuangan & UKT')->id],
            'kategori nonaktif' => [$this->fitk->id, $inactiveLeaf->id],
            'audiens tidak sesuai (khusus dosen)' => [$this->fitk->id, $this->cat('Jadwal Mengajar')->id],
            'audiens tidak sesuai (khusus alumni)' => [$this->fitk->id, $this->cat('Transkrip Nilai')->id],
            'id tidak ada' => [$this->fitk->id, 999999],
        ];

        foreach ($cases as $case => [$facultyId, $categoryId]) {
            $this->actingAs($this->mahasiswa)
                ->postJson('/tickets', $this->payload(['faculty_id' => $facultyId, 'category_id' => $categoryId]))
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'INVALID_CATEGORY');
        }

        $this->assertSame(0, Ticket::count());
    }

    public function test_child_of_inactive_group_is_rejected(): void
    {
        $this->cat('Sistem Akademik & Perkuliahan')->update(['is_active' => false]);

        $this->actingAs($this->mahasiswa)->from('/tickets/new')->post('/tickets', $this->payload())
            ->assertRedirect('/tickets/new')
            ->assertSessionHasErrors('category_id')
            ->assertSessionHasInput('title');

        $this->assertSame(0, Ticket::count());
    }

    public function test_dosen_cannot_file_integritas_dosen(): void
    {
        $dosen = User::factory()->dosen($this->fitk)->create();

        $this->actingAs($dosen)->postJson('/tickets', $this->payload(['category_id' => $this->cat('Integritas Dosen')->id]))
            ->assertStatus(422)->assertJsonPath('error.code', 'INVALID_CATEGORY');
    }

    public function test_extra_category_inherits_audience_from_parent(): void
    {
        $child = Category::factory()->childOf($this->cat('Sistem Akademik & Perkuliahan'))->create(['name' => 'Cuti akademik', 'sla_hours' => 96]);

        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload(['category_id' => $child->id]))->assertSessionHasNoErrors();
        $this->assertSame(96, Ticket::firstOrFail()->sla_target_hours);

        $this->actingAs(User::factory()->dosen($this->fitk)->create())
            ->postJson('/tickets', $this->payload(['category_id' => $child->id]))->assertStatus(422);
    }

    // ---------------------------------------------------------------
    // Validasi isian
    // ---------------------------------------------------------------

    public function test_title_and_description_length_limits(): void
    {
        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload(['title' => str_repeat('a', 151)]))->assertSessionHasErrors('title');
        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload(['description' => str_repeat('a', 2001)]))->assertSessionHasErrors('description');
        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload(['title' => '', 'description' => '']))->assertSessionHasErrors(['title', 'description']);
        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload(['category_id' => '']))->assertSessionHasErrors('category_id');
        $this->assertSame(0, Ticket::count());

        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload(['title' => str_repeat('a', 150), 'description' => str_repeat('b', 2000)]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Ticket::count());
    }

    // ---------------------------------------------------------------
    // Lampiran
    // ---------------------------------------------------------------

    public function test_valid_attachments_are_stored_on_private_disk(): void
    {
        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload([
            'attachments' => [UploadedFile::fake()->image('screenshot krs.png', 300, 200), UploadedFile::fake()->image('foto.jpg'), $this->pdf('surat.pdf')],
        ]))->assertSessionHasNoErrors();

        $attachments = Ticket::firstOrFail()->attachments;
        $this->assertCount(3, $attachments);
        $this->assertEqualsCanonicalizing(['image/png', 'image/jpeg', 'application/pdf'], $attachments->pluck('mime_type')->all());
        $this->assertSame('screenshot krs.png', $attachments->firstWhere('mime_type', 'image/png')->file_name);

        foreach ($attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->file_path);
            $this->assertStringStartsWith('attachments/', $attachment->file_path);
            $this->assertNull($attachment->comment_id);
            $this->assertGreaterThan(0, $attachment->file_size);
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_more_than_three_attachments_are_rejected(): void
    {
        $files = array_map(fn ($i) => UploadedFile::fake()->image("foto{$i}.png"), range(1, 4));

        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload(['attachments' => $files]))->assertSessionHasErrors('attachments');
        $this->assertSame(0, Ticket::count());
    }

    public function test_attachment_larger_than_5mb_is_rejected(): void
    {
        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload([
            'attachments' => [UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf')],
        ]))->assertSessionHasErrors('attachments.0');

        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload([
            'attachments' => [UploadedFile::fake()->create('pas.pdf', 5120, 'application/pdf')],
        ]))->assertSessionHasNoErrors();
    }

    public function test_wrong_file_types_are_rejected_by_extension_and_content(): void
    {
        $cases = [
            'dokumen Word' => UploadedFile::fake()->create('tugas.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'teks berpura-pura PNG' => $this->realFile('foto.png', "MZ ini bukan gambar\n"),
            'PDF asli tapi ekstensi .exe' => $this->pdf('surat.exe'),
            'gambar GIF' => UploadedFile::fake()->image('animasi.gif'),
        ];

        foreach ($cases as $case => $file) {
            $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload(['attachments' => [$file]]))
                ->assertSessionHasErrors('attachments.0');
        }

        $this->assertSame(0, Ticket::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ---------------------------------------------------------------
    // Buat Ulang Tiket (?from=)
    // ---------------------------------------------------------------

    private function rejectedTicket(?string $reason = null): Ticket
    {
        $ticket = $this->ticketFor($this->mahasiswa, $this->cat('Jadwal mata kuliah'), [
            'title' => 'Jadwal bentrok Statistika', 'description' => 'Jadwal Statistika dan Fiqih bentrok hari Senin.',
            'status' => TicketStatus::Ditolak, 'resolved_at' => now(),
            'rejection_reason' => $reason ?? Ticket::REJECTION_WRONG_TARGET.': seharusnya ke FEBI',
        ]);
        Storage::disk('local')->put('attachments/lama/jadwal.png', 'isi-gambar');
        $ticket->attachments()->create(['file_name' => 'jadwal.png', 'file_path' => 'attachments/lama/jadwal.png', 'mime_type' => 'image/png', 'file_size' => 10]);

        return $ticket;
    }

    public function test_recreate_prefills_form_from_rejected_ticket(): void
    {
        $old = $this->rejectedTicket();

        $response = $this->actingAs($this->mahasiswa)->get('/tickets/new?from='.$old->id)
            ->assertOk()
            ->assertSee('Buat ulang dari '.$old->ticket_number)
            ->assertSee('Jadwal bentrok Statistika')
            ->assertSee('Jadwal Statistika dan Fiqih bentrok hari Senin.')
            ->assertSee('jadwal.png')
            ->assertSee('name="from" value="'.$old->id.'"', false);

        $this->assertSame([
            'faculty' => (string) $this->fitk->id,
            'group' => (string) $this->cat('Sistem Akademik & Perkuliahan')->id,
            'category' => (string) $this->cat('Jadwal mata kuliah')->id,
        ], $response->viewData('initial'));
    }

    public function test_recreate_creates_a_new_ticket_with_copied_attachment_and_keeps_old_one(): void
    {
        $old = $this->rejectedTicket();
        $febiAdm = $this->cat('Pelayanan Administrasi & Birokrasi', $this->febi);

        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload([
            'faculty_id' => $this->febi->id, 'category_id' => $febiAdm->id,
            'from' => $old->id, 'copy_attachments' => [$old->attachments->first()->id],
        ]))->assertSessionHasNoErrors();

        $new = Ticket::latest('id')->firstOrFail();
        $this->assertNotSame($old->ticket_number, $new->ticket_number);
        $this->assertSame(TicketStatus::Menunggu, $new->status);
        $this->assertSame(TicketStatus::Ditolak, $old->fresh()->status);
        $this->assertCount(1, $new->attachments);
        $this->assertNotSame('attachments/lama/jadwal.png', $new->attachments->first()->file_path);
        Storage::disk('local')->assertExists($new->attachments->first()->file_path);
    }

    public function test_copied_attachments_count_towards_the_limit_of_three(): void
    {
        $old = $this->rejectedTicket();
        $files = [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png'), UploadedFile::fake()->image('c.png')];

        $this->actingAs($this->mahasiswa)->from('/tickets/new')->post('/tickets', $this->payload([
            'from' => $old->id, 'copy_attachments' => [$old->attachments->first()->id], 'attachments' => $files,
        ]))->assertSessionHasErrors('attachments');

        $this->assertSame(1, Ticket::count());
        $this->assertSame(['attachments/lama/jadwal.png'], Storage::disk('local')->allFiles()); // file baru dibuang lagi
    }

    public function test_recreate_from_someone_elses_ticket_is_forbidden(): void
    {
        $old = $this->rejectedTicket();
        $other = User::factory()->mahasiswa($this->fitk)->create();

        $this->actingAs($other)->get('/tickets/new?from='.$old->id)->assertForbidden();

        // Lampiran orang lain juga tidak bisa "dicuri" lewat copy_attachments.
        $this->actingAs($other)->post('/tickets', $this->payload(['from' => $old->id, 'copy_attachments' => [$old->attachments->first()->id]]));
        $this->assertCount(0, Ticket::where('reporter_id', $other->id)->firstOrFail()->attachments);
    }

    public function test_recreate_is_ignored_for_tickets_rejected_for_other_reasons(): void
    {
        $old = $this->rejectedTicket('Data alumni tidak ditemukan di arsip fakultas.');

        $response = $this->actingAs($this->mahasiswa)->get('/tickets/new?from='.$old->id)->assertOk()->assertDontSee('Buat ulang dari');

        $this->assertNull($response->viewData('from'));
        $this->assertSame('', $response->viewData('initial')['category']);
    }
}
