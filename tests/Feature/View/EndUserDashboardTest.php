<?php

namespace Tests\Feature\View;

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Faculty;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard Pelapor (US-02): 4 kartu dari data asli, tombol Buat Tiket, 5 tiket terbaru.
 */
class EndUserDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_cards_count_only_own_tickets_per_status(): void
    {
        $faculty = Faculty::factory()->create(['code' => 'FITK']);
        $user = User::factory()->mahasiswa($faculty)->create();
        $category = Category::where('faculty_id', $faculty->id)->where('name', 'Keuangan & UKT')->first();
        $make = fn (TicketStatus $status, int $count, ?User $owner = null) => Ticket::factory()->for($category)->status($status)
            ->count($count)->create(['reporter_id' => ($owner ?? $user)->id]);

        $make(TicketStatus::Menunggu, 2);
        $make(TicketStatus::Diproses, 3);
        $make(TicketStatus::Selesai, 1);
        $make(TicketStatus::Ditolak, 1);
        $make(TicketStatus::Menunggu, 4, User::factory()->create()); // milik orang lain

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertSame(['total' => 7, 'menunggu' => 2, 'diproses' => 3, 'selesai' => 1], $response->viewData('stats'));
        $this->assertCount(5, $response->viewData('latest'));
        $response->assertSee('2 tiket menunggu')
            ->assertSee('3 sedang diproses')
            ->assertSee('Buat Tiket Baru')
            ->assertSee(route('tickets.create'))
            ->assertSee('Tiket Terbaru');
    }

    public function test_latest_tickets_are_newest_first_and_link_to_detail(): void
    {
        $faculty = Faculty::factory()->create();
        $user = User::factory()->mahasiswa($faculty)->create();
        $category = Category::where('faculty_id', $faculty->id)->where('name', 'Keuangan & UKT')->first();
        $old = Ticket::factory()->for($category)->create(['reporter_id' => $user->id, 'title' => 'Tiket lama', 'created_at' => now()->subDays(3)]);
        $new = Ticket::factory()->for($category)->create(['reporter_id' => $user->id, 'title' => 'Tiket baru']);
        $closed = Ticket::factory()->for($category)->status(TicketStatus::Selesai)->create(['reporter_id' => $user->id, 'title' => 'Tiket tutup', 'created_at' => now()->subDays(5)]);

        $this->actingAs($user)->get('/dashboard')
            ->assertSeeInOrder(['Tiket baru', 'Tiket lama', 'Tiket tutup'])
            ->assertSee(route('tickets.show', $new))
            ->assertSee(route('history.show', $closed));
    }

    public function test_new_user_sees_empty_state(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertOk()
            ->assertSee('Belum ada tiket')
            ->assertSee('0');
    }
}
