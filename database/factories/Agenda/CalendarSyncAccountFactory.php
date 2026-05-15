<?php

namespace Database\Factories\Agenda;

use App\Models\Agenda\CalendarSyncAccount;
use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CalendarSyncAccount>
 */
class CalendarSyncAccountFactory extends Factory
{
    protected $model = CalendarSyncAccount::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'professional_id' => Professional::factory(),
            'provider' => 'google',
            'provider_user_id' => 'g-'.Str::random(16),
            'provider_email' => fake()->safeEmail(),
            'encrypted_access_token' => Str::random(64),  // cast encrypted aplica Crypt::encryptString
            'encrypted_refresh_token' => Str::random(64),
            'expires_at' => now()->addHour(),
            'google_calendar_id' => 'cal_'.Str::random(20).'@group.calendar.google.com',
            'google_calendar_name_seen' => null,
            'watch_channel_id' => null,
            'watch_channel_resource_id' => null,
            'watch_channel_expires_at' => null,
            'last_polled_at' => null,
            'last_synced_at' => null,
            'status' => 'connected',
        ];
    }

    public function connected(): self
    {
        return $this->state(fn () => ['status' => 'connected']);
    }

    public function disconnected(): self
    {
        return $this->state(fn () => [
            'status' => 'disconnected',
            'last_disconnect_at' => now(),
        ]);
    }

    public function withWatchChannel(): self
    {
        return $this->state(fn () => [
            'watch_channel_id' => fake()->uuid(),
            'watch_channel_resource_id' => Str::random(32),
            'watch_channel_expires_at' => now()->addDays(7),
        ]);
    }
}
