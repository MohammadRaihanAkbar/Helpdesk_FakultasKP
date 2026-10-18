<?php

namespace Tests\Feature\Sso;

use App\Enums\UserType;
use App\Services\Sso\BridgeSsoClient;
use App\Services\Sso\SsoClient;
use App\Services\Sso\SsoException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * BridgeSsoClient diuji dengan Http::fake(): respons Bridge dipalsukan,
 * TIDAK ADA request sungguhan ke server kampus (preventStrayRequests).
 */
class BridgeSsoClientTest extends TestCase
{
    private const BASE = 'https://bridge.test';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['sso.driver' => 'bridge', 'sso.bridge_url' => self::BASE]);
    }

    private function client(): SsoClient
    {
        return app(SsoClient::class);
    }

    public function test_driver_bridge_resolves_bridge_client(): void
    {
        $this->assertInstanceOf(BridgeSsoClient::class, $this->client());
    }

    public function test_login_then_profile_is_mapped_to_sso_profile(): void
    {
        Http::fake([
            self::BASE.'/api/v2/portal/login' => Http::response(['success' => true, 'data' => ['token' => 'abc123']]),
            self::BASE.'/api/v2/profil' => Http::response(config('sso.fake_users.1910110045.response')),
        ]);

        $profile = $this->client()->authenticate('1910110045', 'rahasia');

        $this->assertSame(UserType::Alumni, $profile->userType);
        $this->assertSame('1910110045', $profile->nim);
        $this->assertSame(2023, $profile->graduationYear);
        $this->assertSame('FITK', $profile->facultyCode);
        $this->assertSame('abc123', $profile->token);

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/api/v2/portal/login'
            && $r->method() === 'POST'
            && $r['username'] === '1910110045'
            && $r['password'] === 'rahasia'
            && $r->hasHeader('Accept', 'application/json'));

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/api/v2/profil'
            && $r->hasHeader('Authorization', 'Bearer abc123'));
    }

    public function test_token_at_root_of_response_is_accepted(): void
    {
        Http::fake([
            self::BASE.'/api/v2/portal/login' => Http::response(['token' => 'root-token']),
            self::BASE.'/api/v2/profil' => Http::response(config('sso.fake_users.0412038801.response')),
        ]);

        $profile = $this->client()->authenticate('0412038801', 'x');

        $this->assertSame(UserType::Dosen, $profile->userType);
        $this->assertSame('0412038801', $profile->nidn);
        $this->assertSame('root-token', $profile->token);
    }

    public function test_401_means_wrong_credentials(): void
    {
        Http::fake([self::BASE.'/api/v2/portal/login' => Http::response(['message' => 'Unauthenticated'], 401)]);

        try {
            $this->client()->authenticate('2210110001', 'salah');
            $this->fail('Seharusnya melempar SsoException');
        } catch (SsoException $e) {
            $this->assertSame(SsoException::INVALID_CREDENTIALS, $e->errorCode);
            $this->assertSame('NIM/NIDN atau password salah.', $e->getMessage());
        }
    }

    public function test_connection_failure_means_sso_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(SsoException::class);
        $this->expectExceptionMessage('Layanan SSO kampus sedang tidak dapat dihubungi');

        $this->client()->authenticate('2210110001', 'x');
    }

    public function test_server_error_means_sso_unreachable(): void
    {
        Http::fake([self::BASE.'/*' => Http::response('', 503)]);

        $this->expectExceptionMessage('Layanan SSO kampus sedang tidak dapat dihubungi');

        $this->client()->authenticate('2210110001', 'x');
    }

    public function test_logout_is_best_effort(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->client()->logout('abc123'); // tidak boleh melempar error

        $this->assertTrue(true);
    }
}
