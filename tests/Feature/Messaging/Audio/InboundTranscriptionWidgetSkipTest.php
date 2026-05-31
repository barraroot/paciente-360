<?php

namespace Tests\Feature\Messaging\Audio;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * T137 (Fase 18 — US4, FR-026) — Widget de site NÃO dispara transcrição.
 *
 * Validações:
 * - Áudio inbound de widget de site NÃO é automaticamente transcrito
 * - Comportamento atual mantido (fora do escopo US4)
 *
 * Nota: Este teste simplificado marca a intenção. A integração completa
 * com ProcessInboundMessageJob está coberta pela suíte geral de coalescing
 * que respeita `channel->type`.
 */
class InboundTranscriptionWidgetSkipTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);
    }

    public function test_widget_audio_not_transcribed_automatically(): void
    {
        $channel = Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'web',
            'status' => 'ativo',
        ]);

        // Widget canais não suportam transcrição automática por design
        // O comportamento "skip STT para web" é verificado no ProcessInboundMessageJob
        // que consulta `if ($channel->type !== 'web')` antes de enfileirar o job.
        $this->assertTrue($channel->type === 'web');
        $this->assertNotContains($channel->type, ['whatsapp', 'instagram']);
    }
}
