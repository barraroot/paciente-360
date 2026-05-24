<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Services;

use App\Domain\Privacy\Events\DireitoEsquecimentoExecutado;
use App\Domain\Privacy\Models\ForgettingRequest;
use App\Domain\Privacy\Models\ForgettingStatus;
use App\Models\User;
use App\Support\Lgpd\AnonymizationMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * **T045 (Fase 8 — Lote A US-13.2)** — Executor do Direito ao Esquecimento (Gate 3).
 *
 * Aplica o mapa explícito {@see AnonymizationMap} (Q26) em transação única:
 *   1. ANONIMIZA campos PII em `pacientes` (placeholders fixos por campo).
 *   2. DELETA fisicamente corpos de campo livre (`endereco` jsonb, `anotacoes.texto`).
 *   3. PRESERVA dados sob obrigação legal (controladas, billing, audit, consentimentos).
 *
 * **Princípio I (LGPD)**: a execução é IRREVERSÍVEL. O `ForgettingRequest`
 * permanece como prova de conformidade (5 anos).
 *
 * **R-8-1 Mitigação**: anonimização NÃO deleta linhas — apenas substitui
 * campos. FKs (audit_logs.user_id → patients.id, prescriptions.patient_id, etc.)
 * permanecem válidas. Banner "Dados preservados" na UI sinaliza ao Admin
 * Clínica que o paciente continua referenciável por obrigação legal.
 *
 * @see specs/008-finalizacao-mvp/research.md §1 Q26
 * @see specs/008-finalizacao-mvp/plan.md §9 R-8-1
 */
final class ForgettingExecutor
{
    /**
     * Executa o esquecimento de uma solicitação em status válido.
     *
     * Status válidos para execução:
     *   - Open
     *   - PendingVerification (assumindo que verificação foi confirmada)
     *   - InProgress
     *
     * @throws RuntimeException se a solicitação não está em status válido.
     */
    public function execute(ForgettingRequest $request, User $executedBy): ForgettingRequest
    {
        if ($request->status->isTerminal()) {
            throw new RuntimeException(
                "Solicitação #{$request->id} já está em status terminal: {$request->status->value}",
            );
        }

        $plan = AnonymizationMap::plan($request->patient_id);
        $now = Carbon::now();

        DB::transaction(function () use ($request, $executedBy, $plan, $now): void {
            // 1. Anonimização da tabela `pacientes` (placeholders Q26).
            //    Atualiza diretamente via Query Builder porque Paciente model
            //    pode ter observers que queremos pular nesta operação regulada.
            $patientUpdates = $this->buildPatientUpdates($plan['anonymize'], $request->patient_id);

            if ($patientUpdates !== []) {
                DB::table('pacientes')
                    ->where('id', $request->patient_id)
                    ->where('tenant_id', $request->tenant_id)
                    ->update($patientUpdates);
            }

            // 2. Deleção física de campos de corpo livre.
            //    `endereco` jsonb → NULL (campo na própria tabela pacientes).
            //    `anotacoes.texto` → NULL para todas as anotações do paciente.
            $this->deleteFreeTextFields($request->patient_id, $request->tenant_id);

            // 3. Atualiza o ForgettingRequest com snapshots do que foi feito.
            $request->update([
                'status' => ForgettingStatus::Executed,
                'executed_at' => $now,
                'executed_by_user_id' => $executedBy->id,
                'fields_anonymized' => array_keys($plan['anonymize']),
                'fields_deleted' => $plan['delete'],
                'fields_preserved_reason' => $plan['preserve'],
            ]);
        });

        $request->refresh();

        Event::dispatch(new DireitoEsquecimentoExecutado(
            tenantId: $request->tenant_id,
            patientId: $request->patient_id,
            forgettingRequestId: $request->id,
            executedAt: $now,
            executedByUserId: $executedBy->id,
            fieldsAnonymized: array_keys($plan['anonymize']),
            fieldsDeleted: $plan['delete'],
            fieldsPreservedReason: $plan['preserve'],
        ));

        Log::info('privacy.forgetting.executed', [
            'tenant_id' => $request->tenant_id,
            'patient_id' => $request->patient_id,
            'forgetting_request_id' => $request->id,
            'executed_by_user_id' => $executedBy->id,
            'fields_anonymized_count' => count($plan['anonymize']),
            'fields_deleted_count' => count($plan['delete']),
            'fields_preserved_count' => count($plan['preserve']),
        ]);

        return $request;
    }

    /**
     * Marca uma solicitação como NEGADA (após verificação de identidade falhar).
     */
    public function deny(ForgettingRequest $request, User $executedBy, string $reason): ForgettingRequest
    {
        if ($request->status->isTerminal()) {
            throw new RuntimeException("Solicitação #{$request->id} já está em status terminal.");
        }

        $request->update([
            'status' => ForgettingStatus::Denied,
            'executed_at' => Carbon::now(),
            'executed_by_user_id' => $executedBy->id,
            'denial_reason' => $reason,
        ]);

        return $request->refresh();
    }

    /**
     * Mapeia os placeholders simbólicos do AnonymizationMap para os campos
     * concretos da tabela `pacientes`.
     *
     * Conversão necessária porque o AnonymizationMap usa nomes simbólicos
     * ("convenio_carteirinha") que podem não existir 1:1 nas colunas reais.
     * Aqui aplicamos apenas os campos que de fato existem em `pacientes`.
     *
     * @param array<string, string|null> $anonymizeMap chave=field simbólico, valor=placeholder
     * @return array<string, mixed> chave=coluna real, valor=placeholder
     */
    private function buildPatientUpdates(array $anonymizeMap, int $patientId): array
    {
        // Mapeia campos simbólicos → colunas reais de `pacientes`.
        $columnMap = [
            'nome' => 'nome',
            'cpf' => 'cpf',
            'rg' => 'documento_estrangeiro', // documentos não-CPF na tabela
            'telefone' => 'telefone_primario',
            'email' => 'email',
            'data_nascimento' => 'data_nascimento',
        ];

        $updates = [];

        foreach ($columnMap as $symbolic => $column) {
            if (! array_key_exists($symbolic, $anonymizeMap)) {
                continue;
            }

            $value = $anonymizeMap[$symbolic];
            // Substitui {id} se ainda restar (o AnonymizationMap::plan já fez isso,
            // mas defensive default).
            if (is_string($value)) {
                $value = str_replace('{id}', (string) $patientId, $value);
            }

            $updates[$column] = $value;
        }

        // telefones_secundarios e endereço — categoria DELETE no mapa,
        // mas estão na mesma tabela. Zerar aqui na mesma transação.
        $updates['telefones_secundarios'] = '[]';
        $updates['endereco'] = null;
        $updates['updated_at'] = Carbon::now();

        return $updates;
    }

    /**
     * Deleta corpos de campos de texto livre vinculados ao paciente.
     *
     * - `anotacoes.texto` é NULLificado (a row continua existindo para audit
     *   trail, mas o conteúdo livre é removido). Usa Query Builder direto
     *   para bypass do `AnotacaoImutavelException` no Eloquent (operação
     *   regulada que excepciona a regra de imutabilidade).
     */
    private function deleteFreeTextFields(int $patientId, int $tenantId): void
    {
        // Anotações — bypass do model observer (operação LGPD regulada).
        DB::table('anotacoes')
            ->where('paciente_id', $patientId)
            ->where('tenant_id', $tenantId)
            ->update([
                'texto' => null,
            ]);
        // NB: `anotacoes` não possui coluna `updated_at` (apenas `created_at`).
    }
}
