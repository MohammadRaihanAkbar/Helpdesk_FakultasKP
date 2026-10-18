<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 150)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password'); // PRD menyebut "password_hash"; Laravel memakai nama "password" (isinya tetap hash bcrypt)

            // Identitas login SSO (boleh kosong, tapi jika diisi harus unik)
            $table->string('nim', 20)->nullable()->unique();   // khusus mahasiswa
            $table->string('nidn', 20)->nullable()->unique();  // khusus dosen

            $table->string('program_studi', 100)->nullable();
            $table->string('avatar_url')->nullable();

            // 4 level peran pengguna
            $table->enum('role', ['end_user', 'staff', 'admin', 'super_admin'])->default('end_user');

            // [BARU v6.1] Jenis pelapor: menentukan kategori mana yang tampil di form tiket.
            // Diisi sistem (dari SSO / registrasi hidden link), NULL untuk Staff & Super Admin.
            $table->enum('user_type', ['mahasiswa', 'dosen', 'alumni'])->nullable();

            // [BARU v6.1] Tahun lulus, khusus alumni.
            // Alumni lulus <= 2021 -> registrasi hidden link | lulus >= 2022 -> login SSO
            $table->unsignedSmallInteger('graduation_year')->nullable();

            // origin_faculty_id & faculty_id ditambahkan di migration terpisah (setelah tabel faculties dibuat)

            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
