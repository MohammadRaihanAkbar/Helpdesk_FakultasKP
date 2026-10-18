<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\UserType;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::EndUser,
            'user_type' => UserType::Mahasiswa,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function endUser(?Faculty $originFaculty = null, UserType $userType = UserType::Mahasiswa): static
    {
        return $this->state(fn () => [
            'role' => Role::EndUser,
            'user_type' => $userType,
            'origin_faculty_id' => $originFaculty?->id,
            'faculty_id' => null,
        ]);
    }

    public function staff(?Faculty $faculty = null): static
    {
        return $this->state(fn () => [
            'role' => Role::Staff,
            'user_type' => null,
            'faculty_id' => $faculty?->id,
            'origin_faculty_id' => null,
        ]);
    }

    public function admin(?Faculty $faculty = null): static
    {
        return $this->state(fn () => [
            'role' => Role::Admin,
            'user_type' => null,
            'faculty_id' => $faculty?->id,
            'origin_faculty_id' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role' => Role::SuperAdmin,
            'user_type' => null,
            'faculty_id' => null,
            'origin_faculty_id' => null,
        ]);
    }
}
