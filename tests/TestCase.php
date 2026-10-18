<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Test tidak menjalankan Vite (npm run dev/build), jadi @vite di layout dimatikan.
        $this->withoutVite();
    }
}
