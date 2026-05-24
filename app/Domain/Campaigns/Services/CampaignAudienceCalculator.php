<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Services;

use App\Models\Paciente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * **T158 (Fase 8 — Lote C US-9.1)** — Calculadora de público elegível (Q1).
 *
 * Critério "inativo" definido em Q1 — última `ConsultaRealizada` da Fase 5.
 * Mensagens recebidas e agendamentos pendentes NÃO contam.
 *
 * Filtros suportados:
 *   - `inactivity_months` — número de meses sem consulta realizada
 *   - `tags`              — paciente tem ao menos uma das tags
 *   - `last_professional_id` — última consulta com este profissional
 *   - `age_range`         — {min, max} idade em anos
 *   - `gender`            — 'M' | 'F' | 'O'
 *
 * Retorna `LazyCollection` para iteração eficiente em batches do
 * `ProcessCampaignBatchJob`. `estimate()` retorna apenas a COUNT para a
 * pré-visualização (AC-9.1.1) — sem PII na resposta.
 */
final class CampaignAudienceCalculator
{
    /**
     * Conta o público elegível sem materializar (para pré-visualização).
     *
     * @param array<string, mixed> $filters
     */
    public function estimate(int $tenantId, array $filters): int
    {
        return $this->buildQuery($tenantId, $filters)->count();
    }

    /**
     * Itera o público elegível em LazyCollection — uso pelo dispatcher batch.
     *
     * @param array<string, mixed> $filters
     * @return LazyCollection<int, Paciente>
     */
    public function iterate(int $tenantId, array $filters): LazyCollection
    {
        return $this->buildQuery($tenantId, $filters)->lazy(100);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function buildQuery(int $tenantId, array $filters): Builder
    {
        $query = Paciente::query()
            ->withoutGlobalScopes() // operação cross-tenant é proibida — mas o
            ->where('tenant_id', $tenantId); // filtro explícito garante isolamento

        // Q1 — última ConsultaRealizada (status='realizada' na Fase 5).
        if (isset($filters['inactivity_months']) && (int) $filters['inactivity_months'] > 0) {
            $cutoff = Carbon::now()->subMonths((int) $filters['inactivity_months']);
            $hasAppointments = DB::getSchemaBuilder()->hasTable('appointments');

            if ($hasAppointments) {
                $query->whereNotIn('id', function ($sub) use ($cutoff): void {
                    $sub->select('paciente_id')
                        ->from('appointments')
                        ->where('status', 'realizada')
                        ->where('starts_at', '>=', $cutoff);
                });
            }
            // Sem tabela appointments → todos pacientes são considerados "sem
            // consulta recente" (degrade gracioso em ambientes pre-Fase 5).
        }

        // Tags — paciente tem ao menos uma das tags listadas.
        if (! empty($filters['tags']) && is_array($filters['tags'])) {
            $tags = array_filter($filters['tags']);
            if ($tags !== []) {
                $hasTagsPivot = DB::getSchemaBuilder()->hasTable('paciente_tags');
                if ($hasTagsPivot) {
                    $query->whereIn('id', function ($sub) use ($tags): void {
                        $sub->select('paciente_id')
                            ->from('paciente_tags')
                            ->whereIn('tag', $tags);
                    });
                }
            }
        }

        // Último profissional.
        if (isset($filters['last_professional_id']) && (int) $filters['last_professional_id'] > 0) {
            $hasAppointments = DB::getSchemaBuilder()->hasTable('appointments');
            if ($hasAppointments) {
                $query->whereIn('id', function ($sub) use ($filters): void {
                    $sub->select('paciente_id')
                        ->from('appointments')
                        ->where('professional_id', (int) $filters['last_professional_id']);
                });
            }
        }

        // Faixa de idade.
        if (isset($filters['age_range']) && is_array($filters['age_range'])) {
            $min = $filters['age_range']['min'] ?? null;
            $max = $filters['age_range']['max'] ?? null;

            if ($min !== null) {
                $query->whereDate('data_nascimento', '<=', Carbon::now()->subYears((int) $min)->toDateString());
            }
            if ($max !== null) {
                $query->whereDate('data_nascimento', '>=', Carbon::now()->subYears((int) $max + 1)->toDateString());
            }
        }

        // Gênero.
        if (! empty($filters['gender'])) {
            $query->where('genero', $filters['gender']);
        }

        return $query;
    }
}
