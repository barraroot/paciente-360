<?php

namespace Tests\Unit\Ai;

use App\Domain\Ai\Distribution\Services\AiPersonaSelectorService;
use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Matrix\Services\AiMatrixService;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonaSelectorTest extends TestCase
{
    use RefreshDatabase;

    private AiPersonaSelectorService $selector;

    private AiModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->selector = new AiPersonaSelectorService(new AiMatrixService);
        $this->model = AiModel::factory()->create();
    }

    private function personaOnChannel(Tenant $tenant, string $channel, bool $active = true): AiPersona
    {
        $persona = AiPersona::factory()->forTenant($tenant)->create([
            'ai_model_id' => $this->model->id,
            'is_active' => $active,
        ]);

        AiPersonaChannel::create([
            'tenant_id' => $tenant->id,
            'ai_persona_id' => $persona->id,
            'channel_type' => $channel,
            'is_active' => true,
        ]);

        return $persona;
    }

    #[Test]
    public function it_distributes_evenly_across_active_personas(): void
    {
        $tenant = Tenant::factory()->create();
        $p1 = $this->personaOnChannel($tenant, 'whatsapp');
        $p2 = $this->personaOnChannel($tenant, 'whatsapp');

        $picks = [];
        for ($i = 0; $i < 4; $i++) {
            $picks[] = $this->selector->selectForNewConversation($tenant->id, 'whatsapp')->id;
        }

        $counts = array_count_values($picks);
        $this->assertSame(2, $counts[$p1->id]);
        $this->assertSame(2, $counts[$p2->id]);
        $this->assertLessThanOrEqual(1, max($counts) - min($counts));
    }

    #[Test]
    public function it_returns_null_when_no_active_persona_on_channel(): void
    {
        $tenant = Tenant::factory()->create();
        // persona inativa não conta
        $this->personaOnChannel($tenant, 'whatsapp', active: false);

        $this->assertNull($this->selector->selectForNewConversation($tenant->id, 'whatsapp'));
    }

    #[Test]
    public function distribution_is_isolated_per_tenant_and_channel(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $a1 = $this->personaOnChannel($tenantA, 'whatsapp');
        $b1 = $this->personaOnChannel($tenantB, 'whatsapp');

        $pickA = $this->selector->selectForNewConversation($tenantA->id, 'whatsapp');
        $pickB = $this->selector->selectForNewConversation($tenantB->id, 'whatsapp');

        $this->assertSame($a1->id, $pickA->id);
        $this->assertSame($b1->id, $pickB->id);
    }

    #[Test]
    public function newly_added_persona_enters_the_cycle(): void
    {
        $tenant = Tenant::factory()->create();
        $p1 = $this->personaOnChannel($tenant, 'whatsapp');

        $this->selector->selectForNewConversation($tenant->id, 'whatsapp'); // p1

        $p2 = $this->personaOnChannel($tenant, 'whatsapp');

        $picks = [
            $this->selector->selectForNewConversation($tenant->id, 'whatsapp')->id,
            $this->selector->selectForNewConversation($tenant->id, 'whatsapp')->id,
        ];

        $this->assertContains($p2->id, $picks);
    }
}
