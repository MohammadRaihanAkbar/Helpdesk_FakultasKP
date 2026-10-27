<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * [M6] Halaman utama sekarang halaman depan publik (sampai M5: dialihkan ke /login). /login tetap ada.
     */
    public function test_home_is_the_public_landing_page_and_login_still_works(): void
    {
        $this->get('/')->assertOk()->assertSee('Helpdesk Fakultas Terpadu');
        $this->get('/login')->assertOk();
    }
}
