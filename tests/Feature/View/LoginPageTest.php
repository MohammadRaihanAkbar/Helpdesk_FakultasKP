<?php

namespace Tests\Feature\View;

use App\Models\User;
use Database\Seeders\CategoryCatalogSeeder;
use Database\Seeders\FacultySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman login + komponen form yang dipakainya (x-ui.input, x-ui.alert, x-ui.button).
 */
class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_shows_the_single_login_field(): void
    {
        config(['sso.password_reset_url' => 'https://sso.contoh.ac.id/reset']);

        $this->get('/login')
            ->assertOk()
            ->assertSee('NIM / NIP / Email')
            ->assertDontSee('NIDN')
            ->assertSee('Ingat sesi saya')
            ->assertSee('href="https://sso.contoh.ac.id/reset"', false)
            ->assertSee('images/logo-uinssc.png', false);
    }

    public function test_login_showcase_numbers_come_from_the_database(): void
    {
        $this->seed(FacultySeeder::class);
        $this->seed(CategoryCatalogSeeder::class);

        // 6 fakultas aktif, 11 jenis layanan (leaf katalog standar), SLA tercepat 24 jam.
        $this->get('/login')
            ->assertSeeInOrder(['FITK', 'FUA', 'FDKI', 'FEBI', 'FASYA', 'FASTIH'])
            ->assertSeeInOrder(['6', 'Fakultas terhubung', '11', 'Jenis layanan', '24 jam', 'Target tercepat']);
    }

    public function test_login_showcase_shows_a_dash_without_data(): void
    {
        $this->get('/login')->assertOk()->assertSeeInOrder(['0', 'Fakultas terhubung', '0', 'Jenis layanan', '–', 'Target tercepat']);
    }

    public function test_login_page_does_not_link_to_the_hidden_registration(): void
    {
        $this->get('/login')->assertDontSee('register/alumni');
    }

    public function test_failed_login_shows_an_alert_and_keeps_the_input(): void
    {
        $this->from('/login')->followingRedirects()
            ->post('/login', ['login' => 'tidakada@uinssc.test', 'password' => 'salah'])
            ->assertSee('role="alert"', false)
            ->assertSee('Email atau password salah.')
            ->assertSee('value="tidakada@uinssc.test"', false);
    }

    public function test_throttled_login_shows_the_waiting_message(): void
    {
        $user = User::factory()->staff()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => $user->email, 'password' => 'salah']);
        }

        $this->from('/login')->followingRedirects()
            ->post('/login', ['login' => $user->email, 'password' => 'salah'])
            ->assertSee('Terlalu banyak percobaan login');
    }

    public function test_input_component_marks_errors_for_screen_readers(): void
    {
        $this->withViewErrors(['email' => 'Email wajib diisi.'])
            ->blade('<x-ui.input name="email" label="Email" hint="Email pribadi" />')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="email-hint email-error"', false)
            ->assertSee('Email wajib diisi.');
    }

    public function test_password_input_never_refills_the_old_value(): void
    {
        $this->blade('<x-ui.input name="password" label="Password" type="password" value="rahasia" />')
            ->assertDontSee('rahasia');
    }

    public function test_button_becomes_a_link_when_given_href(): void
    {
        $this->blade('<x-ui.button href="/login" variant="secondary">Kembali</x-ui.button>')
            ->assertSee('<a href="/login"', false)
            ->assertSee('bg-surface text-ink', false); // varian secondary
    }
}
