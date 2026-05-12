<?php

namespace Database\Factories\Messaging;

use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * T038 — Factory para `Message`.
 *
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = fake('pt_BR')->sentence();

        return [
            'direction' => fake()->randomElement(['in', 'out']),
            'sender_type' => 'patient',
            'sender_id' => null,
            'body' => $body,
            'body_searchable' => mb_substr(mb_strtolower($body), 0, 2000),
            'body_preview' => mb_substr($body, 0, 140),
            'content_type' => 'text',
            'template_provider_id' => null,
            'template_variables' => null,
            'external_id' => 'SM'.Str::random(32),
            'external_metadata' => [],
            'status' => 'sent',
            'failure_reason' => null,
            'idempotency_key' => null,
            'sent_at' => now(),
            'delivered_at' => null,
            'read_at' => null,
        ];
    }

    /**
     * Mensagem recebida do paciente.
     */
    public function inbound(): static
    {
        return $this->state([
            'direction' => 'in',
            'sender_type' => 'patient',
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mensagem enviada pelo atendente.
     */
    public function outbound(): static
    {
        return $this->state([
            'direction' => 'out',
            'sender_type' => 'user',
            'idempotency_key' => hash('sha256', Str::random(64)),
        ]);
    }

    /**
     * Mensagem de texto simples.
     */
    public function text(): static
    {
        return $this->state(['content_type' => 'text']);
    }

    /**
     * Mensagem de imagem.
     */
    public function image(): static
    {
        return $this->state([
            'content_type' => 'image',
            'body' => null,
            'body_searchable' => null,
            'body_preview' => null,
        ]);
    }

    /**
     * Mensagem de template aprovado (pré-requisito fora da janela 24h).
     */
    public function template(string $templateId = 'HXabc123'): static
    {
        return $this->state([
            'content_type' => 'template',
            'direction' => 'out',
            'sender_type' => 'user',
            'template_provider_id' => $templateId,
            'template_variables' => ['1' => 'João Silva', '2' => '15/06/2026'],
        ]);
    }

    /**
     * Mensagem com falha no envio.
     */
    public function failed(string $reason = 'Provider timeout'): static
    {
        return $this->state([
            'status' => 'failed',
            'failure_reason' => $reason,
            'direction' => 'out',
            'sender_type' => 'user',
        ]);
    }

    /**
     * Mensagem na fila de envio.
     */
    public function queued(): static
    {
        return $this->state([
            'status' => 'queued',
            'direction' => 'out',
            'sender_type' => 'user',
            'sent_at' => null,
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
