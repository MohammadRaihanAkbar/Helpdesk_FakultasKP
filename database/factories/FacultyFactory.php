<?php

namespace Database\Factories;

use App\Models\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Catatan: membuat Faculty lewat factory memicu event "created",
 * jadi fakultas ini otomatis mendapat 12 kategori katalog standar.
 *
 * @extends Factory<Faculty>
 */
class FacultyFactory extends Factory
{
    public function definition(): array
    {
        $code = Str::upper(fake()->unique()->lexify('F???'));

        return [
            'code' => $code,
            'name' => 'Fakultas '.fake()->words(2, true),
            'slug' => Str::lower($code),
            'is_active' => true,
        ];
    }
}
