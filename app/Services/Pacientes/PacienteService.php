<?php

namespace App\Services\Pacientes;

use App\Events\Paciente\PacienteAtualizado;
use App\Events\Paciente\PacienteCriado;
use App\Exceptions\Pacientes\CpfDuplicadoException;
use App\Exceptions\Pacientes\DedupConflictException;
use App\Models\Paciente;
use App\Models\PacienteConvenio;
use App\Models\User;
use App\Support\Cpf\CpfValidator;
use App\Support\Telefone\TelefoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * T110 — Serviço central de gestão de pacientes (US-3.1).
 *
 * Encapsula criação, atualização e anonimização de pacientes, mantendo
 * toda lógica de negócio fora dos Controllers.
 *
 * @see specs/002-crm-pacientes/spec.md US-3.1
 */
final class PacienteService
{
    public function __construct(
        private readonly DedupService $dedupService,
        private readonly AnonimizacaoService $anonimizacaoService,
    ) {}

    /**
     * Cria um novo paciente com validação de CPF, dedup e vínculos.
     *
     * @param array<string, mixed> $data
     *
     * @throws ValidationException
     * @throws DedupConflictException
     */
    public function create(array $data, ?User $executor = null): Paciente
    {
        return DB::transaction(function () use ($data, $executor): Paciente {
            // Canonicaliza CPF
            if (! empty($data['cpf'])) {
                $data['cpf'] = CpfValidator::canonicalize($data['cpf']);

                if (! CpfValidator::isValid($data['cpf'])) {
                    throw ValidationException::withMessages([
                        'cpf' => [__('paciente.cpf_invalido')],
                    ]);
                }
            } else {
                $data['cpf'] = null;
            }

            // Canonicaliza telefone
            if (! empty($data['telefone_primario'])) {
                try {
                    $data['telefone_primario'] = TelefoneNormalizer::normalize($data['telefone_primario']);
                } catch (\InvalidArgumentException) {
                    // Mantém o valor original se não for possível normalizar
                }
            }

            // Dedup check
            $ignorarDuplicata = (bool) ($data['ignorar_duplicata'] ?? false);

            if (! empty($data['cpf'])) {
                $tenant = app('tenant');
                $candidatos = $this->dedupService->detectDuplicates($data, $tenant);

                if ($candidatos->isNotEmpty()) {
                    if ($ignorarDuplicata) {
                        // ignorar_duplicata=true não é permitido com mesmo CPF (UNIQUE constraint).
                        // Decisão T102: criar paralelo com mesmo CPF viola o UNIQUE(cpf, tenant_id).
                        throw new CpfDuplicadoException;
                    }

                    throw new DedupConflictException($candidatos);
                }
            }

            // Defaults
            $data['origem'] ??= 'presencial';
            $data['origem_origem'] ??= 'manual';
            $data['status'] ??= 'lead';

            // Extrai relações antes de remover do array
            $conveniosData = (array) ($data['convenios'] ?? []);
            $tagsIds = (array) ($data['tags'] ?? []);

            // Remove chaves não-fillable
            unset($data['ignorar_duplicata'], $data['tags'], $data['convenios']);

            // Cria paciente
            $paciente = Paciente::create($data);

            // Vincula tags
            if (! empty($tagsIds)) {
                $this->syncTags($paciente, $tagsIds, $executor);
            }

            // Vincula convênios
            if (! empty($conveniosData)) {
                $this->syncConvenios($paciente, $conveniosData);
            }

            // Dispara evento de auditoria/timeline
            PacienteCriado::dispatch($paciente);

            return $paciente->refresh()->load(['tags', 'convenios', 'profissionalResponsavel']);
        });
    }

    /**
     * Atualiza um paciente existente, rastreando campos alterados.
     *
     * @param array<string, mixed> $data
     */
    public function update(Paciente $paciente, array $data, User $executor): Paciente
    {
        return DB::transaction(function () use ($paciente, $data, $executor): Paciente {
            if (! empty($data['cpf'])) {
                $data['cpf'] = CpfValidator::canonicalize($data['cpf']);

                if (! CpfValidator::isValid($data['cpf'])) {
                    throw ValidationException::withMessages([
                        'cpf' => [__('paciente.cpf_invalido')],
                    ]);
                }
            }

            if (! empty($data['telefone_primario'])) {
                try {
                    $data['telefone_primario'] = TelefoneNormalizer::normalize($data['telefone_primario']);
                } catch (\InvalidArgumentException) {
                    // Mantém original
                }
            }

            $tagsIds = $data['tags'] ?? null;
            $conveniosData = $data['convenios'] ?? null;
            unset($data['tags'], $data['convenios'], $data['ignorar_duplicata']);

            $paciente->fill($data);
            $dirty = $paciente->getDirty();
            $paciente->save();

            // Sincroniza relações se fornecidas
            if ($tagsIds !== null) {
                $this->syncTags($paciente, $tagsIds, $executor);
            }

            if ($conveniosData !== null) {
                $this->syncConvenios($paciente, $conveniosData);
            }

            // Dispara evento se campos rastreados foram alterados
            $trackedFields = config('paciente.timeline.tracked_fields', []);
            $changedTracked = array_intersect_key($dirty, array_flip($trackedFields));

            if (! empty($changedTracked)) {
                $changes = [];
                foreach ($changedTracked as $field => $newValue) {
                    $changes[$field] = ['to' => $newValue];
                }

                PacienteAtualizado::dispatch($paciente, $changes);
            }

            return $paciente->refresh()->load(['tags', 'convenios', 'profissionalResponsavel']);
        });
    }

    /**
     * Anonimiza um paciente (delega para AnonimizacaoService).
     */
    public function anonymize(Paciente $paciente, User $executor): void
    {
        $this->anonimizacaoService->anonymize($paciente, $executor);
    }

    /**
     * Sincroniza tags do paciente.
     *
     * @param array<int, int> $tagIds
     */
    private function syncTags(Paciente $paciente, array $tagIds, ?User $executor): void
    {
        $pivot = [];
        foreach ($tagIds as $tagId) {
            $pivot[$tagId] = [
                'tenant_id' => $paciente->tenant_id,
                'aplicada_por_user_id' => $executor?->id,
                'aplicada_at' => now(),
            ];
        }

        $paciente->tags()->sync($pivot);
    }

    /**
     * Sincroniza convênios do paciente (máximo 2: principal + secundário).
     *
     * @param array<int, array<string, mixed>> $conveniosData
     */
    private function syncConvenios(Paciente $paciente, array $conveniosData): void
    {
        // Remove convênios existentes
        PacienteConvenio::where('paciente_id', $paciente->id)->delete();

        foreach (array_values($conveniosData) as $i => $item) {
            $papel = $i === 0 ? 'principal' : 'secundario';

            PacienteConvenio::create([
                'tenant_id' => $paciente->tenant_id,
                'paciente_id' => $paciente->id,
                'convenio_id' => $item['convenio_id'],
                'numero_carteirinha' => $item['numero_carteirinha'] ?? null,
                'papel' => $item['papel'] ?? $papel,
            ]);
        }
    }
}
