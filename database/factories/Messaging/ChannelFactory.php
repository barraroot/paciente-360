<?php

namespace Database\Factories\Messaging;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * T038 — Factory para `Channel`.
 *
 * `tenant_id` não é setado por default — use `->for(Tenant::factory())` ou
 * `->forTenant($tenant)` em contextos sem tenant resolvido no container.
 *
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['whatsapp', 'instagram', 'web']),
            'name' => fake()->words(3, true),
            'status' => 'ativo',
            'credentials_encrypted' => null,
            'provider_metadata' => [],
            'quality_rating' => null,
            'quality_rating_updated_at' => null,
            'last_health_check_at' => null,
            'auto_send_disabled' => false,
        ];
    }

    /**
     * Canal WhatsApp via Twilio.
     */
    public function whatsapp(): static
    {
        return $this->state([
            'type' => 'whatsapp',
            'provider_metadata' => [
                'messaging_service_sid' => 'MG'.Str::random(32),
                'whatsapp_sender' => 'whatsapp:+551140028922',
                'phone_number_id' => (string) fake()->numerify('###############'),
            ],
        ]);
    }

    /**
     * Canal Instagram via Meta Graph API.
     */
    public function instagram(): static
    {
        return $this->state([
            'type' => 'instagram',
            'provider_metadata' => [
                'page_id' => (string) fake()->numerify('###############'),
                'ig_business_account_id' => (string) fake()->numerify('###############'),
                'ig_username' => fake()->userName(),
            ],
        ]);
    }

    /**
     * Canal Web Widget.
     */
    public function web(): static
    {
        return $this->state([
            'type' => 'web',
            'provider_metadata' => [
                'public_key' => Str::random(64),
            ],
        ]);
    }

    /**
     * Canal desconectado.
     */
    public function desconectado(): static
    {
        return $this->state(['status' => 'desconectado']);
    }

    /**
     * Canal degradado (quality_rating baixo).
     */
    public function degradado(): static
    {
        return $this->state([
            'status' => 'degradado',
            'quality_rating' => 'low',
            'quality_rating_updated_at' => now(),
            'auto_send_disabled' => true,
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
