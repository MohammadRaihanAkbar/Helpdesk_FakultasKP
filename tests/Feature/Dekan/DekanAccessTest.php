<?php

namespace Tests\Feature\Dekan;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\User;

/**
 * Dekan hanya boleh: /dekan/dashboard dan /dekan/laporan (+ ekspor). Semua halaman/aksi lain ditolak,
 * dan peran lain tidak bisa membuka halaman Dekan.
 */
class DekanAccessTest extends DekanTestCase
{
    public function test_dekan_logs_in_with_email_and_lands_on_the_dekan_dashboard(): void
    {
        $this->post('/login', ['login' => 'dekan.fitk@uinssc.test', 'password' => 'password'])
            ->assertRedirect(route('dekan.dashboard'));

        $this->get('/login')->assertRedirect(route('dekan.dashboard'));
        $this->assertSame('dekan.dashboard', Role::Dekan->homeRoute());
        $this->assertSame('Dekan', Role::Dekan->label());
    }

    public function test_dekan_pages_are_open_to_dekan_only(): void
    {
        foreach (['/dekan/dashboard', '/dekan/laporan', '/dekan/laporan/export'] as $url) {
            $this->actingAs($this->dekan)->get($url)->assertOk();

            foreach ([$this->admin, $this->akademik, $this->mahasiswa, $this->superAdmin] as $other) {
                $this->actingAs($other)->get($url)->assertForbidden();
            }
        }

        auth()->logout();
        $this->get('/dekan/dashboard')->assertRedirect('/login');
    }

    public function test_dekan_cannot_open_admin_staff_superadmin_or_reporter_pages(): void
    {
        $ticket = $this->ticket(['created' => now()->subHours(30)]);

        foreach ([
            '/admin/dashboard', '/admin/queue', "/admin/tickets/{$ticket->id}", '/admin/reports', '/admin/reports/export',
            '/admin/staff', '/admin/services', '/staff/dashboard', '/staff/queue', "/staff/tickets/{$ticket->id}",
            '/super-admin/dashboard', '/super-admin/users', '/dashboard', '/tickets', "/tickets/{$ticket->id}",
        ] as $url) {
            $this->actingAs($this->dekan)->get($url)->assertForbidden();
        }
    }

    public function test_dekan_cannot_act_on_tickets(): void
    {
        $ticket = $this->ticket(['created' => now()->subHours(30)]);

        $this->actingAs($this->dekan)->postJson("/admin/tickets/{$ticket->id}/ping", ['message' => 'x'])->assertForbidden();
        $this->actingAs($this->dekan)->patch("/staff/tickets/{$ticket->id}/assign")->assertForbidden();
        $this->actingAs($this->dekan)->post("/tickets/{$ticket->id}/comments", ['body' => 'x'])->assertForbidden();
        $this->assertSame(TicketStatus::Menunggu, $ticket->fresh()->status);
        $this->assertDatabaseCount('ping_logs', 0);

        foreach (['view', 'reply', 'claim', 'handle', 'ping', 'viewReporterIdentity'] as $ability) {
            $this->assertFalse($this->dekan->can($ability, $ticket), $ability);
        }
    }

    public function test_dekan_menu_has_only_dashboard_and_reports(): void
    {
        $this->actingAs($this->dekan)->get('/dekan/dashboard')->assertOk()
            ->assertSee('Laporan &amp; Ekspor', false)
            ->assertDontSee('Monitor Antrean')
            ->assertDontSee('Kelola Staff')
            ->assertDontSee('Pengaturan Layanan')
            ->assertDontSee('Cari nomor tiket');
    }

    public function test_dekan_without_faculty_is_refused(): void
    {
        $orphan = User::factory()->dekan()->create();

        $this->actingAs($orphan)->get('/dekan/dashboard')->assertForbidden();
        $this->actingAs($orphan)->get('/dekan/laporan/export')->assertForbidden();
    }
}
