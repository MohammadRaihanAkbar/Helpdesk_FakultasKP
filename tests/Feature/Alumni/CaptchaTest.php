<?php

namespace Tests\Feature\Alumni;

use App\Models\Faculty;
use App\Models\User;
use App\Rules\ValidCaptcha;
use App\Services\Auth\Captcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * [M6] Captcha buatan sendiri di registrasi alumni: salah / benar / kedaluwarsa / sekali pakai, gambar SVG tanpa teks.
 */
class CaptchaTest extends TestCase
{
    use RefreshDatabase;

    private Faculty $faculty;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.alumni_registration_key' => null, 'helpdesk.captcha.enabled' => true]);
        $this->faculty = Faculty::factory()->create();
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rina Alumni', 'email' => 'rina@example.com', 'nim' => '1410110077', 'graduation_year' => 2018,
            'origin_faculty_id' => $this->faculty->id, 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ], $overrides);
    }

    public function test_form_shows_the_captcha_image_and_refresh_button(): void
    {
        $this->get('/register/alumni')->assertOk()
            ->assertSee(route('register.alumni.captcha'), false)
            ->assertSee('Ganti soal')
            ->assertSee('name="captcha"', false);
    }

    public function test_captcha_image_is_an_svg_without_readable_text_and_stores_only_a_hash(): void
    {
        $response = $this->get('/register/alumni/captcha')->assertOk();

        $this->assertStringStartsWith('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $svg = $response->getContent();
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('<polyline', $svg);
        $this->assertStringNotContainsString('<text', $svg);

        $stored = session(Captcha::SESSION_PREFIX.'alumni');
        $this->assertSame(['hash', 'expires_at'], array_keys($stored));
        $this->assertSame(64, strlen($stored['hash'])); // HMAC-SHA256, bukan jawabannya
        $this->assertEqualsWithDelta(now()->addMinutes(10)->getTimestamp(), $stored['expires_at'], 2);
    }

    public function test_every_image_request_creates_a_new_question(): void
    {
        $this->get('/register/alumni/captcha');
        $first = session(Captcha::SESSION_PREFIX.'alumni')['hash'];
        $hashes = collect(range(1, 5))->map(function () {
            $this->get('/register/alumni/captcha');

            return session(Captcha::SESSION_PREFIX.'alumni')['hash'];
        });

        $this->assertTrue($hashes->contains(fn ($hash) => $hash !== $first));
    }

    public function test_missing_or_wrong_answer_is_rejected(): void
    {
        $this->post('/register/alumni', $this->data())->assertSessionHasErrors('captcha');

        $this->withSession([Captcha::SESSION_PREFIX.'alumni' => Captcha::payload('12')])
            ->post('/register/alumni', $this->data(['captcha' => '13']))
            ->assertSessionHasErrors(['captcha' => ValidCaptcha::MESSAGE]);

        $this->assertDatabaseMissing('users', ['email' => 'rina@example.com']);
    }

    public function test_right_answer_registers_and_spaces_are_ignored(): void
    {
        $this->withSession([Captcha::SESSION_PREFIX.'alumni' => Captcha::payload('12')])
            ->post('/register/alumni', $this->data(['captcha' => ' 1 2 ']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('register.alumni.done'));

        $this->assertDatabaseHas('users', ['email' => 'rina@example.com']);
    }

    public function test_expired_question_is_rejected(): void
    {
        $this->withSession([Captcha::SESSION_PREFIX.'alumni' => Captcha::payload('12')]);
        $this->travel(11)->minutes();

        $this->post('/register/alumni', $this->data(['captcha' => '12']))->assertSessionHasErrors(['captcha' => ValidCaptcha::MESSAGE]);
    }

    public function test_question_is_single_use(): void
    {
        // Jawaban benar, tetapi field lain salah -> soal tetap terpakai; kiriman berikutnya harus memakai soal baru.
        $this->withSession([Captcha::SESSION_PREFIX.'alumni' => Captcha::payload('12')])
            ->post('/register/alumni', $this->data(['captcha' => '12', 'nim' => 'abc']))
            ->assertSessionHasErrors('nim')
            ->assertSessionDoesntHaveErrors('captcha');

        $this->assertNull(session(Captcha::SESSION_PREFIX.'alumni'));
        $this->post('/register/alumni', $this->data(['captcha' => '12']))->assertSessionHasErrors('captcha');
        $this->assertSame(0, User::count());
    }

    public function test_answers_from_the_generated_image_are_checked_by_hash(): void
    {
        $captcha = app(Captcha::class);
        $captcha->generate('alumni');
        session()->put(Captcha::SESSION_PREFIX.'alumni', Captcha::payload('7'));

        $this->assertFalse($captcha->check('alumni', '8'));
        $this->assertFalse($captcha->check('alumni', '7')); // sudah terpakai oleh percobaan sebelumnya
        session()->put(Captcha::SESSION_PREFIX.'alumni', Captcha::payload('7'));
        $this->assertTrue($captcha->check('alumni', '7'));
    }

    public function test_captcha_image_is_throttled(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get('/register/alumni/captcha')->assertOk();
        }

        $this->get('/register/alumni/captcha')->assertStatus(429);
    }
}
