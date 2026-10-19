<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketStatus;
use App\Models\Attachment;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Lampiran disimpan di disk privat dan hanya bisa diunduh lewat route yang mengecek izin (PRD 6.3).
 */
class AttachmentDownloadTest extends TicketTestCase
{
    private Attachment $attachment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->mahasiswa)->post('/tickets', $this->payload(['attachments' => [UploadedFile::fake()->image('bukti krs.png')]]));
        $this->attachment = Attachment::firstOrFail();
    }

    public function test_owner_can_download_with_original_name(): void
    {
        $response = $this->actingAs($this->mahasiswa)->get("/attachments/{$this->attachment->id}")->assertOk();

        $this->assertStringContainsString('bukti krs.png', $response->headers->get('Content-Disposition'));
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_other_end_user_cannot_download(): void
    {
        $this->actingAs(User::factory()->mahasiswa($this->fitk)->create())->get("/attachments/{$this->attachment->id}")->assertForbidden();
    }

    public function test_admin_of_same_faculty_can_but_other_faculty_cannot(): void
    {
        $this->actingAs(User::factory()->admin($this->fitk)->create())->get("/attachments/{$this->attachment->id}")->assertOk();
        $this->actingAs(User::factory()->admin($this->febi)->create())->get("/attachments/{$this->attachment->id}")->assertForbidden();
        $this->actingAs(User::factory()->superAdmin()->create())->get("/attachments/{$this->attachment->id}")->assertForbidden();
    }

    public function test_staff_with_matching_specialty_or_handler_can_download(): void
    {
        $specialist = User::factory()->staff($this->fitk)->create();
        $specialist->specialties()->attach($this->cat('Sistem Akademik & Perkuliahan'));
        $otherStaff = User::factory()->staff($this->fitk)->create();
        $otherStaff->specialties()->attach($this->cat('Keuangan & UKT'));
        $foreignStaff = User::factory()->staff(Faculty::factory()->create())->create();

        $this->actingAs($specialist)->get("/attachments/{$this->attachment->id}")->assertOk();
        $this->actingAs($otherStaff)->get("/attachments/{$this->attachment->id}")->assertForbidden();
        $this->actingAs($foreignStaff)->get("/attachments/{$this->attachment->id}")->assertForbidden();

        $this->attachment->ticket->update(['handler_id' => $otherStaff->id, 'status' => TicketStatus::Diproses]);
        $this->actingAs($otherStaff)->get("/attachments/{$this->attachment->id}")->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post('/logout');
        $this->get("/attachments/{$this->attachment->id}")->assertRedirect('/login');
    }

    public function test_file_is_not_reachable_through_public_storage_url(): void
    {
        $this->get('/storage/'.$this->attachment->file_path)->assertForbidden(); // disk privat: wajib tanda tangan URL
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_missing_file_returns_404_not_500(): void
    {
        Storage::disk('local')->delete($this->attachment->file_path);

        $this->actingAs($this->mahasiswa)->get("/attachments/{$this->attachment->id}")->assertNotFound();
    }
}
