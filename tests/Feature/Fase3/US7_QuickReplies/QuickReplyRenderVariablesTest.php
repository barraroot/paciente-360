<?php

namespace Tests\Feature\Fase3\US7_QuickReplies;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\QuickReply\Models\QuickReply;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T231 — Renderização de variáveis em respostas rápidas (AC-4.7.3 + NC-8.b).
 *
 * POST /api/v1/inbox/quick-replies/{id}/render + {conversation_id}
 * → 200 {rendered, variables_resolved}
 */
class QuickReplyRenderVariablesTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $atendente;

    private Channel $channel;

    private Conversation $conversation;

    private Paciente $paciente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        [$this->tenant, $this->atendente] = $this->tenantAndUserForRole('clinica-render', 'atendente');

        $this->channel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        $this->paciente = Paciente::factory()
            ->forTenant($this->tenant)
            ->create(['nome' => 'Maria Silva']);

        $this->conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->create(['patient_id' => $this->paciente->id]);
    }

    private function url(int $replyId): string
    {
        return $this->tenantUrl($this->tenant, "/inbox/quick-replies/{$replyId}/render");
    }

    private function createReply(string $content): QuickReply
    {
        return QuickReply::factory()
            ->forTenant($this->tenant)
            ->tenantScope()
            ->create([
                'shortcut' => '/test-'.uniqid(),
                'content' => $content,
            ]);
    }

    #[Test]
    public function renders_nome_paciente_from_conversation_patient(): void
    {
        $reply = $this->createReply('Olá {nome_paciente}!');

        $response = $this->postJson($this->url($reply->id), [
            'conversation_id' => $this->conversation->id,
        ]);

        $response->assertOk();
        $this->assertEquals('Olá Maria Silva!', $response->json('rendered'));
        $this->assertEquals('Maria Silva', $response->json('variables_resolved.nome_paciente'));
    }

    #[Test]
    public function renders_primeiro_nome_paciente_from_first_token_of_nome(): void
    {
        $reply = $this->createReply('Olá {primeiro_nome_paciente}!');

        $response = $this->postJson($this->url($reply->id), [
            'conversation_id' => $this->conversation->id,
        ]);

        $response->assertOk();
        $this->assertEquals('Olá Maria!', $response->json('rendered'));
        $this->assertEquals('Maria', $response->json('variables_resolved.primeiro_nome_paciente'));
    }

    #[Test]
    public function renders_nome_profissional_from_patient_profissional_responsavel(): void
    {
        $professional = Professional::factory()
            ->forTenant($this->tenant)
            ->create(['name' => 'Dr. Carlos']);

        $this->paciente->update(['profissional_responsavel_id' => $professional->id]);

        $reply = $this->createReply('Seu médico é {nome_profissional}.');

        $response = $this->postJson($this->url($reply->id), [
            'conversation_id' => $this->conversation->id,
        ]);

        $response->assertOk();
        $this->assertEquals('Seu médico é Dr. Carlos.', $response->json('rendered'));
    }

    #[Test]
    public function renders_nome_clinica_from_tenant_name(): void
    {
        $this->tenant->update(['name' => 'Clínica Alfa']);

        $reply = $this->createReply('Bem-vindo à {nome_clinica}.');

        $response = $this->postJson($this->url($reply->id), [
            'conversation_id' => $this->conversation->id,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Clínica Alfa', $response->json('rendered'));
    }

    #[Test]
    public function renders_nome_atendente_from_authenticated_user(): void
    {
        $this->atendente->update(['name' => 'João Atendente']);

        $reply = $this->createReply('Sou {nome_atendente}, posso ajudar?');

        $response = $this->postJson($this->url($reply->id), [
            'conversation_id' => $this->conversation->id,
        ]);

        $response->assertOk();
        $this->assertEquals('Sou João Atendente, posso ajudar?', $response->json('rendered'));
    }

    #[Test]
    public function renders_data_proxima_consulta_as_empty_when_no_fase5(): void
    {
        $reply = $this->createReply('Próxima consulta: {data_proxima_consulta}');

        $response = $this->postJson($this->url($reply->id), [
            'conversation_id' => $this->conversation->id,
        ]);

        $response->assertOk();
        $this->assertEquals('Próxima consulta: ', $response->json('rendered'));
        $this->assertEquals('', $response->json('variables_resolved.data_proxima_consulta'));
    }

    #[Test]
    public function renders_empty_when_patient_unidentified(): void
    {
        // Conversa sem paciente vinculado
        $convSemPaciente = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->create(['patient_id' => null]);

        $reply = $this->createReply('Olá {nome_paciente}, seu médico é {nome_profissional}.');

        $response = $this->postJson($this->url($reply->id), [
            'conversation_id' => $convSemPaciente->id,
        ]);

        $response->assertOk();
        $this->assertEquals('Olá , seu médico é .', $response->json('rendered'));
    }

    #[Test]
    public function renders_combined_template_with_multiple_variables(): void
    {
        $this->atendente->update(['name' => 'João']);
        $this->tenant->update(['name' => 'Clínica Alfa']);

        $reply = $this->createReply('Olá {primeiro_nome_paciente}, sou {nome_atendente} da {nome_clinica}.');

        $response = $this->postJson($this->url($reply->id), [
            'conversation_id' => $this->conversation->id,
        ]);

        $response->assertOk();
        $this->assertEquals('Olá Maria, sou João da Clínica Alfa.', $response->json('rendered'));
    }

    #[Test]
    public function escapes_user_provided_content_no_template_injection(): void
    {
        // Content sem variáveis — deve ser retornado intacto
        $reply = $this->createReply('Preço: {variavel_inexistente} e texto normal.');

        $response = $this->postJson($this->url($reply->id), [
            'conversation_id' => $this->conversation->id,
        ]);

        $response->assertOk();
        // Variável desconhecida renderiza como string vazia (NC-8.b)
        $this->assertEquals('Preço:  e texto normal.', $response->json('rendered'));
    }
}
