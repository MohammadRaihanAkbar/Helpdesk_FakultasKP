<?php

namespace Tests\Feature\Dekan;

use App\Enums\Role;
use App\Enums\UserType;
use App\Models\User;
use App\Services\Sso\SsoProfile;
use App\Services\Sso\SsoUserSynchronizer;
use Illuminate\Support\Facades\Hash;

/**
 * Siapa yang mengangkat Dekan: Super Admin di /super-admin/users — tunjuk akun yang ada, cabut, atau buat akun lokal.
 * Login SSO ulang tidak mengubah peran Dekan.
 */
class DekanAppointmentTest extends DekanTestCase
{
    public function test_super_admin_appoints_moves_and_revokes_a_dekan(): void
    {
        $dosen = User::factory()->dosen($this->fitk)->create(['name' => 'Prof. Calon Dekan']);

        $this->actingAs($this->superAdmin)->patch("/super-admin/users/{$dosen->id}/role", ['action' => 'appoint_dekan', 'faculty_id' => $this->fitk->id])
            ->assertRedirect()->assertSessionHas('status');
        $dosen->refresh();
        $this->assertSame([Role::Dekan, $this->fitk->id, UserType::Dosen], [$dosen->role, $dosen->faculty_id, $dosen->user_type]);

        $this->actingAs($this->superAdmin)->patch("/super-admin/users/{$dosen->id}/role", ['action' => 'appoint_dekan', 'faculty_id' => $this->febi->id]);
        $this->assertSame($this->febi->id, $dosen->fresh()->faculty_id);

        $this->actingAs($this->superAdmin)->patch("/super-admin/users/{$dosen->id}/role", ['action' => 'revoke'])->assertRedirect();
        $dosen->refresh();
        $this->assertSame([Role::EndUser, null], [$dosen->role, $dosen->faculty_id]);
    }

    public function test_appointing_dekan_requires_a_faculty_and_only_super_admin_may_do_it(): void
    {
        $dosen = User::factory()->dosen($this->fitk)->create();

        $this->actingAs($this->superAdmin)->patch("/super-admin/users/{$dosen->id}/role", ['action' => 'appoint_dekan'])
            ->assertSessionHasErrors('faculty_id');
        $this->actingAs($this->admin)->patch("/super-admin/users/{$dosen->id}/role", ['action' => 'appoint_dekan', 'faculty_id' => $this->fitk->id])
            ->assertForbidden();
        $this->actingAs($this->dekan)->patch("/super-admin/users/{$dosen->id}/role", ['action' => 'appoint_dekan', 'faculty_id' => $this->fitk->id])
            ->assertForbidden();
        $this->assertSame(Role::EndUser, $dosen->fresh()->role);

        // Akun Staff tetap tidak bisa diubah dari halaman ini.
        $this->actingAs($this->superAdmin)->patch("/super-admin/users/{$this->akademik->id}/role", ['action' => 'appoint_dekan', 'faculty_id' => $this->fitk->id])
            ->assertForbidden();
    }

    public function test_super_admin_creates_a_local_dekan_account_that_can_log_in(): void
    {
        $this->actingAs($this->superAdmin)->get('/super-admin/users/dekan/create')->assertOk()->assertSee('Buat Akun Dekan');

        $this->actingAs($this->superAdmin)->post('/super-admin/users/dekan', [
            'name' => 'Dr. Dekan FEBI Baru', 'email' => 'Dekan.Febi@UINSSC.ac.id', 'faculty_id' => $this->febi->id,
            'nip' => '197001012000031001', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('superadmin.users.index', ['role' => 'dekan']));

        $dekan = User::where('email', 'dekan.febi@uinssc.ac.id')->firstOrFail();
        $this->assertSame([Role::Dekan, $this->febi->id, '197001012000031001'], [$dekan->role, $dekan->faculty_id, $dekan->nip]);
        $this->assertTrue(Hash::check('rahasia123', $dekan->password));

        auth()->logout();
        $this->post('/login', ['login' => 'dekan.febi@uinssc.ac.id', 'password' => 'rahasia123'])->assertRedirect(route('dekan.dashboard'));
    }

    public function test_local_dekan_form_validates(): void
    {
        $this->actingAs($this->superAdmin)->post('/super-admin/users/dekan', [
            'name' => '', 'email' => $this->admin->email, 'faculty_id' => 999, 'nip' => '123', 'password' => 'a', 'password_confirmation' => 'b',
        ])->assertSessionHasErrors(['name', 'email', 'faculty_id', 'nip', 'password']);

        $this->actingAs($this->admin)->post('/super-admin/users/dekan', [])->assertForbidden();
        $this->assertSame(2, User::where('role', Role::Dekan)->count());
    }

    public function test_sso_relogin_keeps_the_dekan_role_and_faculty(): void
    {
        $dosen = User::factory()->dosen($this->fitk)->create(['nip' => '198001012005011001']);
        $this->actingAs($this->superAdmin)->patch("/super-admin/users/{$dosen->id}/role", ['action' => 'appoint_dekan', 'faculty_id' => $this->fitk->id]);

        $synced = app(SsoUserSynchronizer::class)->sync(new SsoProfile(
            username: '198001012005011001', userType: UserType::Dosen, name: 'Prof. Dekan SSO', email: 'dekan.sso@uinssc.ac.id',
            nip: '198001012005011001', facultyCode: 'FITK',
        ));

        $this->assertTrue($synced->is($dosen));
        $this->assertSame([Role::Dekan, $this->fitk->id, 'dekan.sso@uinssc.ac.id'], [$synced->role, $synced->faculty_id, $synced->email]);
    }

    public function test_user_list_filters_and_badges_dekan(): void
    {
        $this->actingAs($this->superAdmin)->get('/super-admin/users?role=dekan')->assertOk()
            ->assertSee('Dr. Dekan FITK')->assertSee('Dr. Dekan FEBI')
            ->assertDontSee('Admin FEBI')
            ->assertSee('Cabut Dekan')
            ->assertSee('Buat Akun Dekan');

        $this->actingAs($this->superAdmin)->get('/super-admin/users')->assertOk()->assertSee('Tunjuk Dekan');
    }
}
