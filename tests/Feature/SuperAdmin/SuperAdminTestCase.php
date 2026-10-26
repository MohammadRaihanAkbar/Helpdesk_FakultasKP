<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use Tests\Feature\AdminMonitoring\AdminTestCase;

/**
 * Bekal tes Super Admin (M5). Di atas bekal AdminTestCase (FITK & FEBI dengan katalog standar, mahasiswa FITK,
 * staff FITK berbagai spesialisasi, $staffFebi, $admin FITK, $adminFebi, helper ticket()):
 *
 *   $superAdmin  akun Super Admin (tidak terikat fakultas)
 */
abstract class SuperAdminTestCase extends AdminTestCase
{
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create(['name' => 'Super Admin IT']);
    }

    /** Akun selain Super Admin yang harus ditolak (403) di semua halaman Super Admin. */
    protected function nonSuperAdmins(): array
    {
        return [$this->admin, $this->akademik, $this->mahasiswa];
    }
}
