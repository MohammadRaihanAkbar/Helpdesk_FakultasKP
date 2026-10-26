<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Header keamanan (PRD 6.3): CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_app_pages_send_security_headers(): void
    {
        $user = User::factory()->create();

        foreach ([$this->get('/login'), $this->actingAs($user)->get('/dashboard')] as $response) {
            $response->assertOk()
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Content-Security-Policy');
        }
    }

    public function test_csp_allows_alpine_and_inline_styles_but_not_inline_scripts(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');
        $directives = collect(explode(';', $csp))->mapWithKeys(function (string $part) {
            $tokens = preg_split('/\s+/', trim($part));

            return [array_shift($tokens) => $tokens];
        });

        $this->assertSame(["'self'"], $directives['default-src']);
        $this->assertContains("'unsafe-eval'", $directives['script-src']);       // Alpine.js standar
        $this->assertNotContains("'unsafe-inline'", $directives['script-src']);  // script inline tetap ditolak
        $this->assertContains("'unsafe-inline'", $directives['style-src']);      // @fonts & style="width:..%"
        $this->assertSame(["'self'"], $directives['frame-ancestors']);
        $this->assertSame(["'none'"], $directives['object-src']);
    }

    public function test_vite_dev_server_is_allowed_while_npm_run_dev_is_running(): void
    {
        $hot = tempnam(sys_get_temp_dir(), 'hot');
        file_put_contents($hot, 'http://[::1]:5173'); // isi public/hot saat npm run dev di Laragon

        $csp = (new SecurityHeaders($hot))->policy();
        unlink($hot);

        $this->assertStringContainsString("script-src 'self' 'unsafe-eval' http://[::1]:5173", $csp);
        $this->assertStringContainsString('ws://[::1]:5173', $csp);
    }

    public function test_csp_can_be_switched_to_report_only_or_off(): void
    {
        config(['security.csp.report_only' => true]);
        $this->get('/login')->assertHeader('Content-Security-Policy-Report-Only')->assertHeaderMissing('Content-Security-Policy');

        config(['security.csp.enabled' => false]);
        $this->get('/login')->assertHeaderMissing('Content-Security-Policy')->assertHeaderMissing('Content-Security-Policy-Report-Only')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_extra_sources_from_config_are_added(): void
    {
        config(['security.csp.extra_sources' => ['https://cdn.uinssc.ac.id']]);
        $policy = (new SecurityHeaders(storage_path('framework/tidak-ada-hot')))->policy(); // anggap "npm run dev" tidak berjalan

        $this->assertStringContainsString("img-src 'self' data: blob: https://cdn.uinssc.ac.id", $policy);
    }
}
