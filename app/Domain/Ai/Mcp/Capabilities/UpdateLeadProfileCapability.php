<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\Capabilities;

use App\Domain\Ai\Mcp\Client\McpCallLogger;
use App\Domain\Ai\Mcp\Sandbox\SandboxNeutralizer;
use App\Domain\Crm\Kanban\Services\KanbanCurationService;
use App\Models\Paciente;
use App\Support\Lgpd\PiiScrubber;
use App\Support\Metrics\AiMetricsContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * **T097 (Fase 18 — US3, FR-016/017/021/022/023)** — capability MCP nova
 * (a 7ª) para atualizar o perfil do lead via conversa.
 *
 * Allow-list ESTRITA de campos não-clínicos (FR-016 + FR-023):
 *   name | complaint | preferred_city | urgency | procedure | price_range
 *
 * **NÃO move card** para estados de booking/payment (FR-023) — esses status
 * refletem eventos de domínio reais (SlotReservation::created holder=ia
 * via T101 / ConsultaConfirmada via T102). A IA não tem como burlar
 * porque a allow-list não inclui `funil_coluna_atual_id`.
 *
 * **Bloqueio de PII clínica**: valores que contenham strings características
 * (CID, dosagem mg/ml, diagnóstico explícito) são rejeitados com
 * `error_code: clinical_pii_blocked`. Defesa em profundidade — o modelo
 * pode tentar passar dados clínicos por engano apesar do system prompt.
 */
#[Name('update-lead-profile')]
#[Description('Atualiza UM campo do perfil do lead a partir de uma informação compartilhada pelo paciente: nome, queixa, cidade preferida, urgência, procedimento de interesse ou faixa de preço. NÃO altera o status de booking/pagamento do card — esses estados refletem eventos de domínio aplicados automaticamente pelos listeners.')]
final class UpdateLeadProfileCapability extends BaseMcpCapability
{
    private const ALLOWED_FIELDS = [
        'name',
        'complaint',
        'preferred_city',
        'urgency',
        'procedure',
        'price_range',
    ];

    /**
     * Padrões que indicam PII clínica explícita (defesa em profundidade).
     * Rejeitamos qualquer um — IA deve usar guardrails da Fase 15 antes
     * de chegar aqui, mas redundância é barata.
     */
    private const CLINICAL_PATTERNS = [
        '/\bcid[- ]?\d/i',                  // CID-10
        '/\b\d+\s*(mg|ml|g|mcg|ui)\b/i',    // dosagem
        '/diagn[óo]stic/i',                  // "diagnóstico"/"diagnostic"
        '/posologi/i',
        '/prescri[çc][ãa]o/i',
        '/receit[ao] m[ée]dic/i',
    ];

    public function __construct(
        McpCallLogger $logger,
        AiMetricsContract $metrics,
        private readonly KanbanCurationService $curation,
    ) {
        parent::__construct($logger, $metrics);
    }

    protected function capabilityName(): string
    {
        return 'update-lead-profile';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'field' => $schema->string()
                ->description('Campo do perfil a atualizar.')
                ->enum(self::ALLOWED_FIELDS)
                ->required(),
            'value' => $schema->string()
                ->description('Novo valor (até 500 caracteres, sem PII clínica).')
                ->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function runReal(Request $request, int $tenantId): array
    {
        $field = (string) $request->get('field', '');
        $value = trim((string) $request->get('value', ''));

        if (! in_array($field, self::ALLOWED_FIELDS, true)) {
            return ['error' => 'validation_error', 'field' => $field, 'message' => 'Campo não permitido.'];
        }

        if ($value === '' || mb_strlen($value) > 500) {
            return ['error' => 'validation_error', 'message' => 'Valor inválido (vazio ou >500 caracteres).'];
        }

        if ($this->containsClinicalPii($value)) {
            return ['error' => 'clinical_pii_blocked', 'message' => 'Valor contém indicação de PII clínica (CID/dosagem/diagnóstico). Rejeitado.'];
        }

        // Nome adicional: regex amigável (latim + acentos + espaços/hífen/apóstrofo).
        if ($field === 'name' && preg_match('/^[a-zA-ZÀ-ÿ\s\'-]{2,100}$/u', $value) !== 1) {
            return ['error' => 'validation_error', 'field' => 'name', 'message' => 'Nome em formato inválido.'];
        }

        // Pseudonimiza o valor antes de gravar (defesa LGPD adicional).
        $cleaned = (string) PiiScrubber::scrub($value);

        $context = $this->resolveToolContext($request, $tenantId);

        if ($context->patientId === null) {
            return ['error' => 'patient_not_in_context', 'message' => 'Contexto da conversa não carrega patient_id.'];
        }

        $paciente = Paciente::query()
            ->where('tenant_id', $tenantId)
            ->find($context->patientId);

        if ($paciente === null) {
            return ['error' => 'patient_not_found'];
        }

        $event = $this->curation->applyProfileUpdate(
            paciente: $paciente,
            field: $field,
            valueAfter: $cleaned,
            turnVersion: null,
            toolInvocationId: null,
        );

        if ($event === null) {
            return [
                'patient_id' => $paciente->id,
                'field_updated' => $field,
                'value_before' => $cleaned,
                'value_after' => $cleaned,
                'curation_event_id' => null,
                'applied' => false,
                'no_op' => true,
            ];
        }

        return [
            'patient_id' => $paciente->id,
            'field_updated' => $field,
            'value_before' => $event->value_before,
            'value_after' => $event->value_after,
            'curation_event_id' => $event->id,
            'applied' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function runSandbox(Request $request, int $tenantId): array
    {
        $field = (string) $request->get('field', '');
        $value = (string) $request->get('value', '');

        return SandboxNeutralizer::profileUpdateSynthetic($field, $value);
    }

    private function containsClinicalPii(string $value): bool
    {
        foreach (self::CLINICAL_PATTERNS as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }
}
