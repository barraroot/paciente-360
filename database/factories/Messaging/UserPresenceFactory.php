<?php

namespace Database\Factories\Messaging;

use App\Domain\Messaging\Presence\Models\UserPresence;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * T038 — Factory para `UserPresence`.
 *
 * @extends Factory<UserPresence>
 */
class UserPresenceFactory extends Factory
{
    protected $model = UserPresence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => fake()->randomElement(['online', 'away', 'offline']),
            'last_seen_at' => now()->subMinutes(fake()->numberBetween(0, 60)),
            'max_concurrent_conversations' => 15,
            'current_assigned_count' => fake()->numberBetween(0, 10),
            'notification_preferences' => [
                'sound' => true,
                'browser_push' => false,
                'sound_volume' => 80,
            ],
        ];
    }

    /**
     * Atendente online (heartbeat recente).
     */
    public function online(): static
    {
        return $this->state([
            'status' => 'online',
            'last_seen_at' => now()->subMinutes(fake()->numberBetween(0, 4)),
        ]);
    }

    /**
     * Atendente ausente (away).
     */
    public function away(): static
    {
        return $this->state([
            'status' => 'away',
            'last_seen_at' => now()->subMinutes(fake()->numberBetween(5, 30)),
        ]);
    }

    /**
     * Atendente offline.
     */
    public function offline(): static
    {
        return $this->state([
            'status' => 'offline',
            'last_seen_at' => now()->subHours(fake()->numberBetween(1, 24)),
        ]);
    }

    /**
     * Vincula ao tenant especificado.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
