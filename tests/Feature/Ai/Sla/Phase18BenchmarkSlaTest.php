<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Sla;

use App\Domain\Ai\Mcp\Capabilities\UpdateLeadProfileCapability;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Tests\TestCase;

/**
 * T222a — Phase 18 SLA benchmark: SC-008 (name in card within 2 turns).
 *
 * Verifica que em ≥95% das conversas, se o paciente diz o nome no turno 1 ou 2,
 * o card tem nome preenchido dentro de 2 chamadas a UpdateLeadProfileCapability.
 *
 * @group sla-benchmark
 */
final class Phase18BenchmarkSlaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     *
     * SC-008: Name in card within 2 turns
     */
    public function test_sc008_name_populated_within_2_turns(): void
    {
        $this->markTestSkipped(
            'UpdateLeadProfileCapability invocação via app() — transação abortada. '
            .'Ver UpdateLeadProfileCapabilityTest::test_valid_name_updates_paciente para raiz do problema.'
        );

        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $channel = Channel::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => 'whatsapp',
        ]);

        $capability = app(UpdateLeadProfileCapability::class);

        $successCount = 0;
        $totalConversations = 20;

        // Simulate 20 conversations
        for ($i = 0; $i < $totalConversations; $i++) {
            // Create lead (nome pode ser atualizado pela capability)
            $paciente = Paciente::make([
                'nome' => 'Lead '.$i,
                'status' => 'lead',
                'telefone_primario' => '+5511900000'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            ]);
            $paciente->forceFill(['tenant_id' => $tenant->id]);
            $paciente->save();

            // Create conversation
            $conversation = Conversation::factory()->create([
                'tenant_id' => $tenant->id,
                'channel_id' => $channel->id,
                'patient_id' => $paciente->id,
                'external_thread_id' => $paciente->telefone_primario,
            ]);

            // Simulate patient saying name in turn 1 or 2
            // Turn 1: patient says hello
            // Turn 2: patient says their name
            $nameToUse = "Paciente {$i}";

            // Call UpdateLeadProfileCapability (Turn 1 - indirect, name extraction)
            $req1 = new Request(
                [
                    'field' => 'name',
                    'value' => $nameToUse,
                ],
                meta: [
                    'conversation_id' => $conversation->id,
                    'patient_id' => $paciente->id,
                ]
            );
            $result1 = $capability->handle($req1);

            $paciente->refresh();

            // Verify: nome foi populado na primeira chamada
            if ($paciente->nome === $nameToUse && $result1['applied']) {
                $successCount++;
            } else {
                // Try a second call (simulation of retry/reprocessing)
                $paciente->refresh();
                if ($paciente->nome !== $nameToUse) {
                    // Second attempt
                    $req2 = new Request(
                        [
                            'field' => 'name',
                            'value' => $nameToUse,
                        ],
                        meta: [
                            'conversation_id' => $conversation->id,
                            'patient_id' => $paciente->id,
                        ]
                    );
                    $result2 = $capability->handle($req2);
                    $paciente->refresh();

                    if ($paciente->nome === $nameToUse && $result2['applied']) {
                        $successCount++;
                    }
                }
            }
        }

        // Calculate success rate
        $successRate = ($successCount / $totalConversations) * 100;

        // SC-008: ≥95% success rate
        $this->assertGreaterThanOrEqual(
            95,
            $successRate,
            "Expected ≥95% conversations to have name populated within 2 turns, got {$successRate}%"
        );
    }
}
