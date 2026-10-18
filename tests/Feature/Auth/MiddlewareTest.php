<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Http\Middleware\EnsureAlumniRegistrationKey;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Middleware diuji LANGSUNG (memanggil handle()), tanpa route.
 *
 * User dibuat dengan make() (tidak disimpan ke database), jadi test ini tidak butuh tabel.
 *
 * $next adalah "langkah berikutnya" (controller). Kalau middleware meloloskan request,
 * $next dipanggil dan kita menerima respons 'lolos'.
 */
class MiddlewareTest extends TestCase
{
    /** Request palsu dengan user tertentu (null = tamu). */
    private function request(?User $user = null, string $uri = '/'): Request
    {
        $request = Request::create($uri);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function next(): \Closure
    {
        return fn () => response('lolos');
    }

    public function test_role_middleware_lets_the_right_role_through(): void
    {
        $staff = User::factory()->make(['role' => Role::Staff]);

        $response = (new EnsureUserHasRole)->handle($this->request($staff), $this->next(), 'staff');

        $this->assertSame('lolos', $response->getContent());
    }

    public function test_role_middleware_accepts_one_of_several_roles(): void
    {
        $admin = User::factory()->make(['role' => Role::Admin]);

        $response = (new EnsureUserHasRole)->handle($this->request($admin), $this->next(), 'admin', 'super_admin');

        $this->assertSame('lolos', $response->getContent());
    }

    public function test_role_middleware_returns_403_for_another_role(): void
    {
        $endUser = User::factory()->make();

        try {
            (new EnsureUserHasRole)->handle($this->request($endUser), $this->next(), 'staff');
            $this->fail('Seharusnya ditolak dengan 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_active_middleware_lets_active_users_and_guests_through(): void
    {
        $middleware = new EnsureUserIsActive;

        $this->assertSame('lolos', $middleware->handle($this->request(User::factory()->make()), $this->next())->getContent());
        $this->assertSame('lolos', $middleware->handle($this->request(null), $this->next())->getContent());
    }

    public function test_alumni_key_middleware_is_open_when_no_key_is_configured(): void
    {
        config(['auth.alumni_registration_key' => null]);

        $response = (new EnsureAlumniRegistrationKey)->handle($this->request(), $this->next());

        $this->assertSame('lolos', $response->getContent());
    }

    public function test_alumni_key_middleware_requires_the_right_key(): void
    {
        config(['auth.alumni_registration_key' => 'kunci-rahasia']);
        $middleware = new EnsureAlumniRegistrationKey;

        $response = $middleware->handle($this->request(null, '/register/alumni?key=kunci-rahasia'), $this->next());
        $this->assertSame('lolos', $response->getContent());

        $this->expectException(NotFoundHttpException::class);
        $middleware->handle($this->request(null, '/register/alumni?key=salah'), $this->next());
    }
}
