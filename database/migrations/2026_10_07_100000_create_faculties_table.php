<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel 1: faculties — data master 6 fakultas UIN SSC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculties', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();   // FITK, FUA, FDKI, FEBI, FASYA, FASTIH
            $table->string('name', 150);
            $table->string('slug', 50)->unique();   // fitk, fua, ... (dipakai di URL)
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculties');
    }
};
