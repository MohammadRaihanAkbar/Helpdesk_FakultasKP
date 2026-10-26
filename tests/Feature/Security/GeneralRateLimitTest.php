<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batas umum 60 request/menit per akun yang login (PRD 6.3), limiter 'authenticated' di grup web.
 */
class GeneralRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_user_is_limited_to_60_requests_per_minute(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        for ($i = 1; $i <= AppServiceProvider::AUTHENTICATED_PER_MINUTE; $i++) {
            $this->actingAs($user)->get('/dashboard')->assertOk();
        }

        $this->actingAs($user)->get('/dashboard')->assertStatus(429)->assertSee('Terlalu banyak');
        // Dihitung per akun: pengguna lain (mis. di Wi-Fi kampus yang sama) tidak ikut terkena.
        $this->actingAs($other)->get('/dashboard')->assertOk();
    }

    public function test_guests_are_not_limited_by_the_general_limiter(): void
    {
        for ($i = 1; $i <= AppServiceProvider::AUTHENTICATED_PER_MINUTE + 5; $i++) {
            $this->get('/login')->assertOk();
        }
    }
}
