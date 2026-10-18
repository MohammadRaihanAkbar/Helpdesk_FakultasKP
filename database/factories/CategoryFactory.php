<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Kategori TAMBAHAN fakultas (is_standard = false). Kategori standar dibuat oleh CategoryCatalogService.
 *
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'faculty_id' => Faculty::factory(),
            'parent_id' => null,
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'sla_hours' => 72,
            'is_standard' => false,
            'is_active' => true,
        ];
    }

    /** Jadikan Jenis Masalah di bawah kelompok tertentu (fakultasnya ikut kelompok). */
    public function childOf(Category $group): static
    {
        return $this->state(['faculty_id' => $group->faculty_id, 'parent_id' => $group->id]);
    }
}
