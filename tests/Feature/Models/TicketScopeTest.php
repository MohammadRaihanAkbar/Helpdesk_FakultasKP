<?php

namespace Tests\Feature\Models;

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Faculty;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scope Ticket::forStaff(), open(), closed().
 */
class TicketScopeTest extends TestCase
{
    use RefreshDatabase;

    private function category(Faculty $faculty, string $name): Category
    {
        return $faculty->categories()->where('name', $name)->firstOrFail();
    }

    public function test_for_staff_only_returns_tickets_of_own_faculty_and_specialty_groups(): void
    {
        $fitk = Faculty::factory()->create();
        $other = Faculty::factory()->create();

        $staff = User::factory()->staff($fitk)->create();
        $staff->specialties()->attach([
            $this->category($fitk, 'Sistem Akademik & Perkuliahan')->id, // kelompok berisi jenis masalah
            $this->category($fitk, 'Keuangan & UKT')->id,                 // kelompok tanpa jenis masalah
        ]);

        $krs = Ticket::factory()->for($this->category($fitk, 'KRS belum di-ACC'))->create();      // anak kelompok spesialisasi
        $ukt = Ticket::factory()->for($this->category($fitk, 'Keuangan & UKT'))->create();        // kelompok spesialisasi
        $it = Ticket::factory()->for($this->category($fitk, 'Akun, Jaringan & IT'))->create();    // bukan spesialisasi
        $otherKrs = Ticket::factory()->for($this->category($other, 'KRS belum di-ACC'))->create(); // fakultas lain

        $ids = Ticket::forStaff($staff)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$krs->id, $ukt->id], $ids);
        $this->assertNotContains($it->id, $ids);
        $this->assertNotContains($otherKrs->id, $ids);
    }

    public function test_staff_without_specialty_or_faculty_sees_nothing(): void
    {
        $faculty = Faculty::factory()->create();
        Ticket::factory()->for($this->category($faculty, 'Keuangan & UKT'))->create();

        $this->assertSame(0, Ticket::forStaff(User::factory()->staff($faculty)->create())->count());
        $this->assertSame(0, Ticket::forStaff(User::factory()->superAdmin()->create())->count());
    }

    public function test_open_and_closed_scopes(): void
    {
        $category = $this->category(Faculty::factory()->create(), 'Keuangan & UKT');

        foreach (TicketStatus::cases() as $status) {
            Ticket::factory()->for($category)->status($status)->create();
        }

        $this->assertEqualsCanonicalizing(
            [TicketStatus::Menunggu, TicketStatus::Diproses],
            Ticket::open()->pluck('status')->all()
        );
        $this->assertEqualsCanonicalizing(
            [TicketStatus::Selesai, TicketStatus::Ditolak],
            Ticket::closed()->pluck('status')->all()
        );
        $this->assertSame('Menunggu', TicketStatus::Menunggu->label());
        $this->assertSame('danger', TicketStatus::Ditolak->badgeColor());
    }

    public function test_ticket_casts_and_relations(): void
    {
        $category = $this->category(Faculty::factory()->create(), 'Keuangan & UKT');
        $ticket = Ticket::factory()->for($category)->create();

        $this->assertInstanceOf(TicketStatus::class, $ticket->fresh()->status);
        $this->assertTrue($ticket->reporter->is(User::first()));
        $this->assertTrue($ticket->faculty->is($category->faculty));
        $this->assertSame(72, $ticket->sla_target_hours);
    }
}
