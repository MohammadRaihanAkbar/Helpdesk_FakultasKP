<?php

namespace Tests\Feature\View;

use App\Http\Requests\Auth\AlumniRegistrationRequest;
use App\Models\Faculty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman registrasi alumni (hidden link) + komponen x-ui.select.
 */
class AlumniRegistrationPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.alumni_registration_key' => null]);
    }

    public function test_registration_page_shows_all_us01b_fields(): void
    {
        Faculty::factory()->create(['code' => 'FITK', 'name' => 'Fakultas Ilmu Tarbiyah dan Keguruan']);

        $this->get('/register/alumni')
            ->assertOk()
            ->assertSeeInOrder(['Nama Lengkap', 'Email Pribadi', 'NIM Lama', 'Tahun Lulus', 'Fakultas Asal', 'Password', 'Ulangi Password'])
            ->assertSee('FITK · Fakultas Ilmu Tarbiyah dan Keguruan');
    }

    public function test_inactive_faculties_are_not_offered(): void
    {
        Faculty::factory()->create(['code' => 'FLAMA', 'is_active' => false]);

        $this->get('/register/alumni')->assertDontSee('FLAMA');
    }

    public function test_form_action_keeps_the_hidden_link_key(): void
    {
        config(['auth.alumni_registration_key' => 'kunci-rahasia']);

        $this->get('/register/alumni?key=kunci-rahasia')
            ->assertOk()
            ->assertSee('register/alumni?key=kunci-rahasia', false);
    }

    public function test_sso_message_is_shown_next_to_the_graduation_year(): void
    {
        $faculty = Faculty::factory()->create();

        $this->from('/register/alumni')->followingRedirects()
            ->post('/register/alumni', [
                'name' => 'Alumni Baru', 'email' => 'baru@example.com', 'nim' => '2010110001',
                'graduation_year' => 2023, 'origin_faculty_id' => $faculty->id,
                'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
            ])
            ->assertSee('id="graduation_year-error"', false)
            ->assertSee(AlumniRegistrationRequest::ALUMNI_USE_SSO_MESSAGE)
            ->assertSee('value="baru@example.com"', false); // isian lama tetap ada
    }

    public function test_select_component_keeps_the_selected_option(): void
    {
        $this->blade('<x-ui.select name="fakultas" label="Fakultas" :options="[1 => \'FITK\', 2 => \'FEBI\']" selected="2" placeholder="Pilih" />')
            ->assertSee('<option value="2" selected', false)
            ->assertSee('<option value="">Pilih</option>', false);
    }
}
