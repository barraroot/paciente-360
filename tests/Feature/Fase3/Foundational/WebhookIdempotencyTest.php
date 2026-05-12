<?php

namespace Tests\Feature\Fase3\Foundational;

use App\Domain\Messaging\Infrastructure\Webhook\WebhookEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T050 — TDD: Idempotência de webhook via WebhookEventRecorder.
 *
 * Research R9: tabela `messaging_webhook_events` com UNIQUE (provider, external_id)
 * + INSERT ON CONFLICT DO NOTHING garante que retries do provider não criam
 * entradas duplicadas.
 *
 * Não requer Model WebhookEvent — usa DB::table() diretamente para asserts,
 * permitindo execução antes do T037 (model paralelo) concluir.
 */
class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private WebhookEventRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recorder = new WebhookEventRecorder;
    }

    #[Test]
    public function it_records_a_new_webhook_event_with_insert_or_ignore(): void
    {
        $provider = 'twilio';
        $externalId = 'SM'.uniqid();
        $payload = ['MessageSid' => $externalId, 'Body' => 'Olá', 'From' => '+5511999999999'];

        $result = $this->recorder->recordOnce(
            provider: $provider,
            externalId: $externalId,
            payload: $payload,
            signatureVerified: true,
            eventType: 'message.inbound',
        );

        $this->assertNotNull($result, 'Deve retornar dados do registro inserido');

        $count = DB::table('messaging_webhook_events')
            ->where('provider', $provider)
            ->where('external_id', $externalId)
            ->count();

        $this->assertSame(1, $count, 'Exatamente 1 registro deve existir no banco');
    }

    #[Test]
    public function it_returns_null_when_duplicate_external_id_for_same_provider(): void
    {
        $provider = 'twilio';
        $externalId = 'SM'.uniqid();
        $payload = ['MessageSid' => $externalId];

        // Primeira inserção — deve ter sucesso
        $first = $this->recorder->recordOnce(
            provider: $provider,
            externalId: $externalId,
            payload: $payload,
            signatureVerified: true,
            eventType: 'message.inbound',
        );

        $this->assertNotNull($first, 'Primeira inserção deve retornar dados');

        // Segunda inserção com mesmo (provider, external_id) — duplicata
        $second = $this->recorder->recordOnce(
            provider: $provider,
            externalId: $externalId,
            payload: ['MessageSid' => $externalId, 'retry' => true],
            signatureVerified: true,
            eventType: 'message.inbound',
        );

        $this->assertNull($second, 'Duplicata deve retornar null (INSERT ON CONFLICT DO NOTHING)');

        // Confirma que apenas 1 registro existe
        $count = DB::table('messaging_webhook_events')
            ->where('provider', $provider)
            ->where('external_id', $externalId)
            ->count();

        $this->assertSame(1, $count, 'Apenas 1 registro deve existir após retry');
    }

    #[Test]
    public function it_allows_same_external_id_across_different_providers(): void
    {
        $externalId = 'MSG'.uniqid();
        $payload = ['id' => $externalId];

        $twilioResult = $this->recorder->recordOnce(
            provider: 'twilio',
            externalId: $externalId,
            payload: $payload,
            signatureVerified: true,
            eventType: 'message.inbound',
        );

        $metaResult = $this->recorder->recordOnce(
            provider: 'meta',
            externalId: $externalId,
            payload: $payload,
            signatureVerified: true,
            eventType: 'message.inbound',
        );

        $this->assertNotNull($twilioResult, 'Twilio deve registrar com sucesso');
        $this->assertNotNull($metaResult, 'Meta deve registrar com sucesso (provider diferente)');

        $twilioCount = DB::table('messaging_webhook_events')
            ->where('provider', 'twilio')
            ->where('external_id', $externalId)
            ->count();

        $metaCount = DB::table('messaging_webhook_events')
            ->where('provider', 'meta')
            ->where('external_id', $externalId)
            ->count();

        $this->assertSame(1, $twilioCount);
        $this->assertSame(1, $metaCount);
    }

    #[Test]
    public function it_validates_signature_flag_persisted(): void
    {
        $externalId = 'SM'.uniqid();

        $this->recorder->recordOnce(
            provider: 'twilio',
            externalId: $externalId,
            payload: ['MessageSid' => $externalId],
            signatureVerified: false,
            eventType: 'message.inbound',
        );

        $row = DB::table('messaging_webhook_events')
            ->where('provider', 'twilio')
            ->where('external_id', $externalId)
            ->first();

        $this->assertNotNull($row);
        $this->assertFalse((bool) $row->signature_verified, 'signature_verified deve ser false conforme passado');
    }
}
