<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\Role;
use App\Enums\UserType;
use App\Models\User;

/**
 * Daftar Pengguna + tunjuk/cabut Admin Fakultas (US-18, F-SPA-02, F-SPA-04).
 */
class UserManagementTest extends SuperAdminTestCase
{
    private User $dosen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dosen = User::factory()->dosen($this->fitk)->create([
            'name' => 'Dr. Budi Dosen', 'email' => 'dosen@uinssc.test', 'nip' => '198804122015031001',
        ]);
    }

    public function test_only_super_admin_can_open_users_and_change_roles(): void
    {
        foreach ($this->nonSuperAdmins() as $user) {
            $this->actingAs($user)->get('/super-admin/users')->assertForbidden();
            $this->actingAs($user)->patch("/super-admin/users/{$this->dosen->id}/role", ['action' => 'appoint', 'faculty_id' => $this->fitk->id])->assertForbidden();
        }

        $this->assertSame(Role::EndUser, $this->dosen->fresh()->role);
    }

    public function test_list_shows_all_users_read_only(): void
    {
        $this->actingAs($this->superAdmin)->get('/super-admin/users')
            ->assertOk()
            ->assertSee('Dr. Budi Dosen')
            ->assertSee('198804122015031001')
            ->assertSee('Ahmad Mahasiswa')
            ->assertSee('Sari Akademik')
            ->assertSee('Dikelola Admin Fakultas')  // akun staff tanpa tombol
            ->assertSee('Akun Anda');               // diri sendiri tanpa tombol
    }

    public function test_search_by_nip_nim_email_or_name(): void
    {
        $searches = [
            '198804122015' => 'Dr. Budi Dosen',                // NIP (awalan)
            $this->mahasiswa->nim => 'Ahmad Mahasiswa',       // NIM
            'dosen@uinssc' => 'Dr. Budi Dosen',                // email
            'Ahmad' => 'Ahmad Mahasiswa',                      // nama
        ];

        foreach ($searches as $q => $expected) {
            $response = $this->actingAs($this->superAdmin)->get('/super-admin/users?q='.urlencode($q))->assertOk();
            $names = $response->viewData('users')->pluck('name');
            $this->assertContains($expected, $names, "Pencarian {$q}");
            $this->assertCount(1, $names, "Pencarian {$q} seharusnya hanya 1 hasil");
        }

        $this->actingAs($this->superAdmin)->get('/super-admin/users?role=admin')
            ->assertSee('Admin FITK')->assertSee('Admin FEBI')->assertDontSee('Ahmad Mahasiswa');
    }

    public function test_super_admin_appoints_a_lecturer_as_faculty_admin(): void
    {
        $before = $this->dosen->only(['name', 'email', 'nip', 'nim', 'nidn', 'user_type', 'origin_faculty_id', 'is_active', 'password']);

        $this->actingAs($this->superAdmin)
            ->patch("/super-admin/users/{$this->dosen->id}/role", ['action' => 'appoint', 'faculty_id' => $this->febi->id])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->dosen->refresh();
        $this->assertSame(Role::Admin, $this->dosen->role);
        $this->assertSame($this->febi->id, $this->dosen->faculty_id);
        // HANYA role & faculty_id yang berubah (US-18): jenis pelapor dll. tetap.
        $this->assertSame($before, $this->dosen->only(array_keys($before)));
        $this->assertSame(UserType::Dosen, $this->dosen->user_type);

        // Langsung bisa membuka dashboard Admin FEBI.
        $this->actingAs($this->dosen)->get('/admin/dashboard')->assertOk()->assertSee('Fakultas Ekonomi dan Bisnis Islam');
    }

    public function test_existing_admin_can_be_moved_to_another_faculty(): void
    {
        $this->actingAs($this->superAdmin)
            ->patch("/super-admin/users/{$this->admin->id}/role", ['action' => 'appoint', 'faculty_id' => $this->febi->id]);

        $this->assertSame([Role::Admin, $this->febi->id], [$this->admin->fresh()->role, $this->admin->fresh()->faculty_id]);
    }

    public function test_super_admin_revokes_an_admin_back_to_end_user(): void
    {
        $this->dosen->forceFill(['role' => Role::Admin, 'faculty_id' => $this->fitk->id])->save();

        $this->actingAs($this->superAdmin)
            ->patch("/super-admin/users/{$this->dosen->id}/role", ['action' => 'revoke'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->dosen->refresh();
        $this->assertSame(Role::EndUser, $this->dosen->role);
        $this->assertNull($this->dosen->faculty_id);
        $this->assertSame(UserType::Dosen, $this->dosen->user_type);
        $this->assertSame($this->fitk->id, $this->dosen->origin_faculty_id);

        $this->actingAs($this->dosen)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($this->dosen)->get('/dashboard')->assertOk();
    }

    public function test_revoking_a_non_admin_is_rejected(): void
    {
        $this->actingAs($this->superAdmin)
            ->patch("/super-admin/users/{$this->dosen->id}/role", ['action' => 'revoke'])
            ->assertSessionHasErrors('rule');

        $this->assertSame(Role::EndUser, $this->dosen->fresh()->role);
    }

    public function test_appoint_requires_an_existing_faculty(): void
    {
        $this->actingAs($this->superAdmin)
            ->patch("/super-admin/users/{$this->dosen->id}/role", ['action' => 'appoint'])
            ->assertSessionHasErrors(['faculty_id' => 'Pilih fakultas tempat Admin ini bertugas.']);

        $this->actingAs($this->superAdmin)
            ->patch("/super-admin/users/{$this->dosen->id}/role", ['action' => 'appoint', 'faculty_id' => 9999])
            ->assertSessionHasErrors('faculty_id');

        $this->assertSame(Role::EndUser, $this->dosen->fresh()->role);
    }

    public function test_staff_and_super_admin_accounts_cannot_be_changed(): void
    {
        $otherSuperAdmin = User::factory()->superAdmin()->create();

        foreach ([$this->akademik, $this->superAdmin, $otherSuperAdmin] as $target) {
            $this->actingAs($this->superAdmin)
                ->patch("/super-admin/users/{$target->id}/role", ['action' => 'appoint', 'faculty_id' => $this->fitk->id])
                ->assertForbidden();
        }

        $this->assertSame(Role::Staff, $this->akademik->fresh()->role);
        $this->assertSame(Role::SuperAdmin, $this->superAdmin->fresh()->role);
    }

    public function test_super_admin_cannot_create_staff_or_manage_specialties_and_categories(): void
    {
        $this->actingAs($this->superAdmin)->post('/admin/staff', [
            'name' => 'Staff Siluman', 'email' => 'siluman@uinssc.test', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertForbidden();
        $this->actingAs($this->superAdmin)->put("/admin/staff/{$this->akademik->id}/specialties", ['categories' => []])->assertForbidden();
        $this->actingAs($this->superAdmin)->post('/admin/services', ['name' => 'Kategori Siluman', 'sla_hours' => 24])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'siluman@uinssc.test']);
        $this->assertDatabaseMissing('categories', ['name' => 'Kategori Siluman']);
        $this->assertSame(1, $this->akademik->specialties()->count());
    }
}
