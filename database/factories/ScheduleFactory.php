<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate a safe start time (8AM–3PM)
        $startTimestamp = fake()->dateTimeBetween('08:00', '15:00')->getTimestamp();

        // Add 1–4 hours to ensure end > start
        $endTimestamp = $startTimestamp + rand(3600, 14400);

        return [
            'day_index' => fake()->numberBetween(0, 4), // Mon–Fri
            'start_time' => date('H:i:s', $startTimestamp),
            'end_time' => date('H:i:s', $endTimestamp),
        ];
    }

    /**
     * Force a specific day (for full-week generation)
     */
    public function forDay(int $day): static
    {
        return $this->state(fn () => [
            'day_index' => $day,
        ]);
    }
}
