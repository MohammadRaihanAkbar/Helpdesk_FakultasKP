<?php

namespace Tests\Feature\Dekan;

use App\Models\User;
use Tests\Feature\SuperAdmin\SuperAdminTestCase;

/**
 * Bekal tes Dekan (M6). Di atas bekal SuperAdminTestCase (FITK & FEBI + katalog, mahasiswa FITK, staff FITK,
 * $staffFebi, $admin FITK, $adminFebi, $superAdmin, helper ticket()):
 *
 *   $dekan      Dekan FITK
 *   $dekanFebi  Dekan FEBI (untuk uji batas fakultas)
 */
abstract class DekanTestCase extends SuperAdminTestCase
{
    protected User $dekan;

    protected User $dekanFebi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dekan = User::factory()->dekan($this->fitk)->create(['name' => 'Dr. Dekan FITK', 'email' => 'dekan.fitk@uinssc.test']);
        $this->dekanFebi = User::factory()->dekan($this->febi)->create(['name' => 'Dr. Dekan FEBI']);
    }

    /** Isi CSV unduhan Dekan sebagai baris array (tanpa BOM). */
    protected function csv(User $user, string $query = ''): array
    {
        $response = $this->actingAs($user)->get('/dekan/laporan/export?format=csv'.$query)->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));

        return array_map(fn ($line) => str_getcsv($line, ',', '"', ''), array_values(array_filter(explode("\n", substr($response->streamedContent(), 3)))));
    }
}
