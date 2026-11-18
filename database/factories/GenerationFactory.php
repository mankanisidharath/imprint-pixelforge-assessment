<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Features\Pixelforge\GenerationOptions;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'prompt' => fake()->sentence(),
            'size' => GenerationOptions::DEFAULT_SIZE,
            'quality' => GenerationOptions::DEFAULT_QUALITY,
            'reference_images' => [],
            'status' => GenerationStatus::Queued,
            'estimated_charge_min_nano' => 1,
            'estimated_charge_max_nano' => 1,
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_id' => null,
            'status' => GenerationStatus::Queued,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_id' => 'gen_'.fake()->uuid(),
            'status' => GenerationStatus::Queued,
        ]);
    }

    public function succeeded(): static
    {
        return $this->accepted()->state(fn (array $attributes) => [
            'status' => GenerationStatus::Succeeded,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->accepted()->state(fn (array $attributes) => [
            'status' => GenerationStatus::Failed,
            'completed_at' => now(),
        ]);
    }
}
