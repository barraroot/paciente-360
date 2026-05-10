<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para `AuditLog` — usado APENAS em testes (US-2.4 — FR-035).
 *
 * Diferente das outras factories: para popular `created_at` customizado
 * o caller deve usar o estate ou passar `created_at` em `overrides`.
 *
 * A tabela `audit_logs` é append-only (trigger PG bloqueia UPDATE/DELETE).
 * INSERTs com `created_at` arbitrário continuam funcionando — o trigger
 * só dispara em UPDATE/DELETE.
 *
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'user_id' => null,
            'actor_type' => 'system',
            'action' => 'test.factory.event',
            'auditable_type' => null,
            'auditable_id' => null,
            'payload' => [],
            'ip' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'request_id' => null,
            'created_at' => now(),
        ];
    }

    /**
     * Vincula a um tenant explícito.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Vincula a um usuário ator (actor_type = 'user').
     */
    public function byUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
            'actor_type' => 'user',
            'tenant_id' => $user->tenant_id,
        ]);
    }

    /**
     * Define uma action customizada.
     */
    public function action(string $action): static
    {
        return $this->state(fn (): array => [
            'action' => $action,
        ]);
    }
}
