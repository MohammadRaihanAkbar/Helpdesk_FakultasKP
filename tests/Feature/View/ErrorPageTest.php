<?php

namespace Tests\Feature\View;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Halaman error berbahasa Indonesia (resources/views/errors).
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_page_is_in_indonesian(): void
    {
        $this->get('/alamat-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('Kembali ke beranda');
    }

    public function test_403_page_shows_the_middleware_message(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/dashboard')
            ->assertForbidden()
            ->assertSee('Akses ditolak')
            ->assertSee('Anda tidak memiliki akses ke halaman ini.');
    }

    public function test_419_page_explains_the_expired_form(): void
    {
        Route::get('/_test/419', fn () => abort(419));

        $this->get('/_test/419')->assertStatus(419)->assertSee('Sesi formulir berakhir');
    }

    public function test_500_page_hides_the_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/500', fn () => throw new \RuntimeException('detail rahasia'));

        $this->get('/_test/500')
            ->assertStatus(500)
            ->assertSee('Terjadi kesalahan di server')
            ->assertDontSee('detail rahasia');
    }
}
