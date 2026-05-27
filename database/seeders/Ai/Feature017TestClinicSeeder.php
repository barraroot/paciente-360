<?php

declare(strict_types=1);

namespace Database\Seeders\Ai;

use App\Domain\Ai\KnowledgeBase\Models\AiKnowledgeBase;
use App\Domain\Ai\KnowledgeBase\Services\AiKnowledgeBaseService;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\WorkContext\Services\AiWorkContextService;
use App\Models\Agenda\AppointmentType;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Throwable;

/**
 * Feature 017 — provisiona a "clínica de teste" (estilo Dra. Daniele) para o
 * plano de teste manual/E2E: contexto de trabalho, tipo de atendimento e base de
 * conhecimento (RAG). Idempotente. NÃO é dado de produção.
 *
 * Tenant alvo: env FEATURE017_TENANT_SLUG, senão o primeiro tenant existente.
 *
 *   vendor/bin/sail artisan db:seed --class="Database\\Seeders\\Ai\\Feature017TestClinicSeeder"
 */
class Feature017TestClinicSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = $this->resolveTenant();

        if ($tenant === null) {
            $this->command?->warn('Nenhum tenant encontrado — crie um tenant antes de rodar este seeder.');

            return;
        }

        app()->instance('tenant', $tenant);
        $this->command?->info("Provisionando clínica de teste no tenant #{$tenant->id} ({$tenant->slug}).");

        $this->seedWorkContext($tenant);
        $this->seedAppointmentType($tenant);
        $this->seedKnowledgeBase($tenant);

        $this->command?->info('Pronto. Veja specs/017-ai-conversation-humanization/test-plan.md (seções 3 e 4).');
    }

    private function resolveTenant(): ?Tenant
    {
        $slug = env('FEATURE017_TENANT_SLUG');

        if (is_string($slug) && $slug !== '') {
            return Tenant::query()->where('slug', $slug)->first();
        }

        return Tenant::query()->orderBy('id')->first();
    }

    private function seedWorkContext(Tenant $tenant): void
    {
        app(AiWorkContextService::class)->upsert($tenant->id, [
            'tone' => 'acolhedor, caloroso, com emojis (💛 ✨ 😊), frases curtas',
            'services' => [
                ['nome' => 'Consulta para enxaqueca e cefaleia', 'descricao' => 'Avaliação individualizada de ~1h (gatilhos, sono, rotina)'],
            ],
            'pricing' => [
                ['item' => 'Consulta', 'valor_a_vista' => 'R$300', 'valor_cartao' => 'R$330', 'observacao' => 'Emite nota fiscal para reembolso'],
            ],
            'locations' => [
                ['cidade' => 'Aracaju', 'endereco' => 'Centro Médico Jardim Europa'],
                ['cidade' => 'Itabaiana'],
            ],
            'deposit_policy' => ['exige_sinal' => true, 'percentual' => 20, 'meio' => 'PIX', 'texto' => 'Sinal de 20% antecipado, abatido na consulta'],
            'qualification_questions' => [
                'Com que frequência as crises costumam acontecer atualmente?',
                'Essas dores costumam atrapalhar seu trabalho ou as atividades do dia a dia?',
                'Você já passou por avaliação específica para enxaqueca com algum médico antes?',
            ],
            'free_form' => 'Avaliação cuidadosa e individualizada antes de qualquer conduta — não é protocolo único. Investiga crises, tratamentos anteriores, gatilhos, rotina e sono. Além da prescrição, pode usar bloqueios anestésicos, pontos-gatilho e toxina botulínica em casos selecionados.',
        ]);

        $this->command?->info('  ✓ Contexto de trabalho.');
    }

    private function seedAppointmentType(Tenant $tenant): void
    {
        AppointmentType::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'consulta-enxaqueca-cefaleia'],
            [
                'nome' => 'Consulta enxaqueca/cefaleia',
                'duration_minutes' => 60,
                'buffer_minutes' => 0,
                'valor_particular' => 300.00,
                'cor' => '#7C3AED',
                'descricao' => 'Avaliação individualizada de ~1h',
                'is_active' => true,
            ],
        );

        $this->command?->info('  ✓ Tipo de atendimento (R$300, 60min).');
    }

    private function seedKnowledgeBase(Tenant $tenant): void
    {
        $name = 'FAQ Dra. Daniele — Enxaqueca';

        if (AiKnowledgeBase::query()->where('tenant_id', $tenant->id)->where('name', $name)->exists()) {
            $this->command?->info('  ✓ Base de conhecimento já existe (pulando).');

            return;
        }

        $markdown = <<<'MD'
        # Sobre a abordagem
        A Dra. Daniele atende dores crônicas, enxaqueca e cefaleia com investigação clínica aprofundada — olha o contexto completo do paciente, não a dor isolada. Muitos chegam após vários tratamentos sem entender a causa das crises.

        # Duração e formato
        Consulta particular de ~1 hora, para entender histórico, gatilhos e definir os próximos passos de forma individualizada.

        # Recursos terapêuticos
        Conforme a avaliação: orientações, ajustes terapêuticos, acompanhamento próximo, bloqueios anestésicos, pontos-gatilho musculares e toxina botulínica em situações selecionadas.

        # Confirmação e reserva
        A reserva é confirmada com sinal de 20% via PIX, abatido do valor. A equipe humana envia a chave e confirma após o comprovante.
        MD;

        try {
            $base = app(AiKnowledgeBaseService::class)->create(
                ['name' => $name, 'description' => 'Base de teste (feature 017)', 'markdown_content' => $markdown],
                tenantId: $tenant->id,
                userId: null,
            );

            // Associa à primeira persona ativa do tenant (se houver).
            $persona = AiPersona::query()->where('tenant_id', $tenant->id)->where('is_active', true)->first();
            if ($persona !== null) {
                $persona->knowledgeBases()->syncWithoutDetaching($persona->pivotTenantMap([$base->id]));
                $this->command?->info('  ✓ Base de conhecimento criada e associada à persona ativa (indexação na fila ai).');
            } else {
                $this->command?->warn('  ! Base criada, mas nenhuma persona ativa para associar — associe manualmente.');
            }
        } catch (Throwable $e) {
            $this->command?->warn('  ! Falha ao criar/indexar a base (verifique OPENAI_API_KEY e a fila ai): '.Str::limit($e->getMessage(), 120));
        }
    }
}
