<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Faculty;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Kelola Staff (US-12, F-ADM-01, F-ADM-02).
 */
class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private Faculty $faculty;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faculty = Faculty::factory()->create(['code' => 'FITK']);
        $this->admin = User::factory()->admin($this->faculty)->create();
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Siti Staff Akademik',
            'email' => 'Siti.Staff@UINSSC.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $overrides);
    }

    public function test_admin_creates_staff_in_own_faculty_automatically(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/staff', $this->validData());

        $staff = User::where('email', 'siti.staff@uinssc.test')->firstOrFail();
        $response->assertRedirect(route('admin.staff.specialties.edit', $staff));

        $this->assertSame(Role::Staff, $staff->role);
        $this->assertSame($this->faculty->id, $staff->faculty_id);
        $this->assertNull($staff->user_type);
        $this->assertTrue($staff->is_active);
        $this->assertTrue(Hash::check('rahasia123', $staff->password));
    }

    public function test_faculty_and_role_from_input_are_ignored(): void
    {
        $other = Faculty::factory()->create();

        $this->actingAs($this->admin)->post('/admin/staff', $this->validData(['faculty_id' => $other->id, 'role' => 'admin']));

        $staff = User::where('email', 'siti.staff@uinssc.test')->firstOrFail();
        $this->assertSame($this->faculty->id, $staff->faculty_id);
        $this->assertSame(Role::Staff, $staff->role);
    }

    public function test_new_staff_can_log_in_with_initial_password(): void
    {
        $this->actingAs($this->admin)->post('/admin/staff', $this->validData());
        auth()->logout();

        $this->post('/login', ['login' => 'siti.staff@uinssc.test', 'password' => 'rahasia123'])
            ->assertRedirect('/staff/dashboard');
    }

    public function test_create_staff_validation(): void
    {
        User::factory()->create(['email' => 'dipakai@uinssc.test']);

        $this->actingAs($this->admin)->post('/admin/staff', $this->validData(['email' => 'dipakai@uinssc.test']))->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->post('/admin/staff', $this->validData(['email' => 'bukan-email']))->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->post('/admin/staff', $this->validData(['name' => '']))->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post('/admin/staff', $this->validData(['password' => 'pendek', 'password_confirmation' => 'pendek']))->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->post('/admin/staff', $this->validData(['password_confirmation' => 'beda12345']))->assertSessionHasErrors('password');

        $this->assertSame(0, User::where('role', Role::Staff)->count());
    }

    public function test_list_shows_staff_of_own_faculty_with_specialties_status_and_active_tickets(): void
    {
        $staff = User::factory()->staff($this->faculty)->create(['name' => 'Rudi Staff', 'email' => 'rudi@uinssc.test']);
        $ukt = Category::where('faculty_id', $this->faculty->id)->where('name', 'Keuangan & UKT')->first();
        $staff->specialties()->attach($ukt);
        Ticket::factory()->for($ukt)->count(2)->create(['handler_id' => $staff->id, 'status' => TicketStatus::Diproses]);
        Ticket::factory()->for($ukt)->create(['handler_id' => $staff->id, 'status' => TicketStatus::Selesai]);

        User::factory()->staff($this->faculty)->inactive()->create(['name' => 'Tono Nonaktif']);
        User::factory()->staff(Faculty::factory()->create())->create(['name' => 'Staff Fakultas Lain']);

        $this->actingAs($this->admin)->get('/admin/staff')
            ->assertOk()
            ->assertSee('Rudi Staff')
            ->assertSee('rudi@uinssc.test')
            ->assertSee('Keuangan &amp; UKT', false)
            ->assertSee('Tono Nonaktif')
            ->assertSee('Nonaktif')
            ->assertSee('Cakupan spesialisasi')
            ->assertDontSee('Staff Fakultas Lain');

        $this->assertSame(2, $this->get('/admin/staff')->viewData('staff')->firstWhere('name', 'Rudi Staff')->active_tickets_count);
    }

    public function test_admin_can_deactivate_and_reactivate_staff(): void
    {
        $staff = User::factory()->staff($this->faculty)->create();

        $this->actingAs($this->admin)->patch("/admin/staff/{$staff->id}/status", ['is_active' => '0'])->assertRedirect();
        $this->assertFalse($staff->fresh()->is_active);

        $this->actingAs($this->admin)->patch("/admin/staff/{$staff->id}/status", ['is_active' => '1']);
        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_deactivated_staff_cannot_log_in_and_keeps_tickets(): void
    {
        $staff = User::factory()->staff($this->faculty)->create(['email' => 'nonaktif@uinssc.test']);
        $ticket = Ticket::factory()->for(Category::where('faculty_id', $this->faculty->id)->where('name', 'Keuangan & UKT')->first())
            ->create(['handler_id' => $staff->id, 'status' => TicketStatus::Diproses]);

        $this->actingAs($this->admin)->patch("/admin/staff/{$staff->id}/status", ['is_active' => '0']);
        auth()->logout();

        $this->post('/login', ['login' => 'nonaktif@uinssc.test', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertSame($staff->id, $ticket->fresh()->handler_id);
    }

    public function test_admin_cannot_touch_staff_of_another_faculty_or_non_staff_accounts(): void
    {
        $foreignStaff = User::factory()->staff(Faculty::factory()->create())->create();
        $otherAdmin = User::factory()->admin($this->faculty)->create();
        $endUser = User::factory()->create();

        foreach ([$foreignStaff, $otherAdmin, $endUser] as $target) {
            $this->actingAs($this->admin)->patch("/admin/staff/{$target->id}/status", ['is_active' => '0'])->assertForbidden();
            $this->actingAs($this->admin)->get("/admin/staff/{$target->id}/specialties")->assertForbidden();
            $this->actingAs($this->admin)->put("/admin/staff/{$target->id}/specialties", ['category_ids' => []])->assertForbidden();
            $this->assertTrue($target->fresh()->is_active);
        }
    }

    public function test_non_admin_cannot_manage_staff(): void
    {
        $staff = User::factory()->staff($this->faculty)->create();

        $this->actingAs($staff)->get('/admin/staff')->assertForbidden();
        $this->actingAs($staff)->post('/admin/staff', $this->validData())->assertForbidden();
        $this->actingAs(User::factory()->superAdmin()->create())->post('/admin/staff', $this->validData())->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'siti.staff@uinssc.test']);
    }

    public function test_create_page_renders(): void
    {
        $this->actingAs($this->admin)->get('/admin/staff/create')
            ->assertOk()
            ->assertSee('Tambah Staff Baru')
            ->assertSee('name="password_confirmation"', false);
    }
}
