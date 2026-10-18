<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Enums\UserType;
use App\Models\Category;
use App\Models\Faculty;
use App\Models\Rating;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Semua seeder M.Raihan berjalan di SQLite (test) dan datanya terbaca lewat model & relasinya.
 * Di MySQL/MariaDB dicek manual dengan: php artisan migrate:fresh --seed
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Jalankan DatabaseSeeder sebelum setiap test di class ini.
        // (Properti $seed = true hanya berlaku saat migrasi PERTAMA dalam satu proses test,
        // jadi untuk SQLite in-memory lebih aman memanggil seed() langsung.)
        $this->seed();
    }

    public function test_seeders_create_the_demo_data(): void
    {
        $this->assertSame(6, Faculty::count());
        $this->assertSame(72, Category::count());
        $this->assertSame(1, User::where('role', Role::SuperAdmin)->count());
        $this->assertSame(6, User::where('role', Role::Admin)->count());
        $this->assertSame(3, User::where('role', Role::Staff)->count());
        $this->assertSame(4, User::where('role', Role::EndUser)->count());
        $this->assertSame(4, Ticket::count());
        $this->assertSame(5, Rating::first()->score);
    }

    public function test_seeded_staff_queue_matches_specialties(): void
    {
        $staffAkademik = User::where('email', 'staff.akademik.fitk@uinssc.test')->first();
        $staffAlumni = User::where('email', 'staff.alumni.fitk@uinssc.test')->first();

        // Tiket demo "KRS belum di-ACC" masuk antrean staff akademik, bukan staff alumni.
        $this->assertSame(['KRS saya belum di-ACC Dosen PA'], Ticket::forStaff($staffAkademik)->pluck('title')->all());
        $this->assertSame(2, Ticket::forStaff($staffAlumni)->count()); // transkrip + PDDIKTI
    }

    public function test_demo_ticket_relations_can_be_read_through_models(): void
    {
        // Tiket demo "diproses": punya 1 pesan publik + 1 internal note, 1 Ping, 1 notifikasi in-app.
        $inProgress = Ticket::where('status', TicketStatus::Diproses)->firstOrFail();
        $this->assertSame(2, $inProgress->comments()->count());
        $this->assertSame(1, $inProgress->comments()->where('is_internal_note', true)->count());
        $this->assertSame('staff.umum.fitk@uinssc.test', $inProgress->handler->email);
        $this->assertSame('admin.fitk@uinssc.test', $inProgress->pingLogs->first()->admin->email);
        $this->assertSame(1, $inProgress->handler->inAppNotifications()->count());

        // Tiket demo "selesai": bukti penyelesaian + rating dari pelapor.
        $done = Ticket::where('status', TicketStatus::Selesai)->firstOrFail();
        $this->assertSame('application/pdf', $done->resolutionProofs->first()->mime_type);
        $this->assertTrue($done->rating->user->is($done->reporter));
        $this->assertSame(UserType::Alumni, $done->reporter_type);

        // Tiket demo "menunggu": lampiran form awal, kategori leaf di bawah "Sistem Akademik & Perkuliahan".
        $waiting = Ticket::where('status', TicketStatus::Menunggu)->firstOrFail();
        $this->assertSame(1, $waiting->attachments()->count());
        $this->assertSame('Sistem Akademik & Perkuliahan', $waiting->category->group()->name);
        $this->assertNull($waiting->handler);
    }
}
