<?php

namespace App\Services\Convenios;

use App\Models\Convenio;
use App\Models\Tenant;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * T242 — Serviço CRUD para catálogo de convênios por tenant (US-3.5).
 *
 * Regras:
 *  - Exclusão bloqueada quando o convênio está vinculado a pacientes.
 *  - `toggleActive` alterna `is_active` sem expor status atual ao caller.
 */
final class ConvenioService
{
    /**
     * Cria um convênio no catálogo do tenant.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data, Tenant $tenant): Convenio
    {
        return Convenio::create([
            'tenant_id' => $tenant->id,
            'nome' => $data['nome'],
            'codigo_ans' => $data['codigo_ans'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Atualiza campos do convênio.
     *
     * @param array<string, mixed> $data
     */
    public function update(Convenio $convenio, array $data): Convenio
    {
        $convenio->fill(array_filter(
            $data,
            static fn ($value) => $value !== null,
        ));

        // is_active pode ser false explicitamente — filter removeria
        if (array_key_exists('is_active', $data)) {
            $convenio->is_active = (bool) $data['is_active'];
        }

        $convenio->save();

        return $convenio->refresh();
    }

    /**
     * Exclui o convênio do catálogo.
     *
     * @throws ConflictHttpException se houver pacientes vinculados.
     */
    public function delete(Convenio $convenio): void
    {
        if ($convenio->pacienteConvenios()->exists()) {
            throw new ConflictHttpException('convenio.em_uso');
        }

        $convenio->delete();
    }

    /**
     * Alterna o status `is_active` do convênio.
     */
    public function toggleActive(Convenio $convenio): Convenio
    {
        $convenio->update(['is_active' => ! $convenio->is_active]);

        return $convenio->refresh();
    }
}
