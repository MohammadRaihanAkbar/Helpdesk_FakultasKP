<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Enums\UserType;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tiket "menunggu" sederhana untuk test. Biasanya dipanggil dengan kategori tertentu:
 *   Ticket::factory()->for($category)->create();
 *
 * faculty_id & sla_target_hours otomatis mengikuti kategori (snapshot seperti aturan bisnis).
 *
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_number' => 'TKT-'.now()->format('Ym').'-'.fake()->unique()->numerify('####'),
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'status' => TicketStatus::Menunggu,
            'reporter_id' => User::factory(),
            'reporter_type' => UserType::Mahasiswa,
            'category_id' => Category::factory(),
            'faculty_id' => fn (array $attributes) => Category::find($attributes['category_id'])->faculty_id,
            'sla_target_hours' => fn (array $attributes) => Category::find($attributes['category_id'])->sla_hours,
        ];
    }

    public function status(TicketStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'resolved_at' => in_array($status, TicketStatus::closedStatuses(), true) ? now() : null,
        ]);
    }
}
