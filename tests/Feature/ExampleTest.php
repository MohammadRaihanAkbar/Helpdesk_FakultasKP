<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Halaman utama mengarahkan tamu ke halaman login.
     */
    public function test_home_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
