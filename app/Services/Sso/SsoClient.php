<?php

namespace App\Services\Sso;

/**
 * "Kontrak" untuk semua cara login SSO.
 *
 * Kenapa pakai interface?
 *   Controller & LoginService hanya tahu "ada sesuatu yang bisa authenticate()".
 *   Implementasinya dipilih lewat config/sso.php -> 'driver' (lihat AppServiceProvider):
 *     - BridgeSsoClient : memanggil Bridge API kampus sungguhan
 *     - FakeSsoClient   : simulasi lokal untuk development & test
 *   Jadi ganti mode cukup ubah SSO_DRIVER di .env, tanpa mengubah kode.
 */
interface SsoClient
{
    /**
     * Cek username + password ke SSO dan kembalikan profilnya.
     *
     * @throws SsoException jika kredensial salah, SSO tidak bisa dihubungi, atau profil tidak dikenali
     */
    public function authenticate(string $username, string $password): SsoProfile;

    /**
     * Akhiri sesi di SSO. Best effort: kegagalan TIDAK boleh menggagalkan logout aplikasi.
     */
    public function logout(string $token): void;
}
