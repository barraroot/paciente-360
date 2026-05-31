<?php

declare(strict_types=1);

namespace App\Domain\Ai\Persona\Models;

use App\Casts\AsJsonArray;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Feature 018 (US6) — sessão isolada do chat de teste de Persona.
 *
 * Cada sessão pertence a um admin (FR-043 — isolamento por usuário) e
 * carrega um snapshot da Persona+WorkContext no momento da abertura
 * (FR-039 — versão publicada OU draft em edição).
 *
 * mcp_token_id aponta para um Sanctum PAT emitido com ability mcp.invoke
 * + metadata sandbox=true; revogado pelo close() do service (T176, FR-051).
 *
 * Mensagens da sessão usam messaging_messages com sandbox=true +
 * sandbox_session_id (T018) — evita schema duplicado.
 *
 * @property string $id
 * @property int $tenant_id
 * @property int $persona_id
 * @property array<string, mixed> $persona_snapshot
 * @property int $admin_user_id
 * @property int|null $mcp_token_id
 * @property string $status open|closed|archived
 * @property Carbon|null $closed_at
 * @property Carbon|null $archived_at
 */
class PersonaTestSession extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'persona_test_sessions';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'persona_id',
        'persona_snapshot',
        'admin_user_id',
        'mcp_token_id',
        'status',
        'closed_at',
        'archived_at',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'persona_snapshot' => AsJsonArray::class,
            'closed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
