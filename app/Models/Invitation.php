<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Model `Invitation` — convite de usuário interno (US-2.2, FR-025).
 *
 * Tabela: `invitations` (schema em data-model.md § 6).
 * Usa `BelongsToTenant` para isolamento automático por tenant (Princípio II).
 *
 * Token: gerado externamente pelo `InvitationService`; somente o hash
 * (`token_hash`) é armazenado nesta tabela — o token claro nunca toca
 * o banco de dados (LGPD — Princípio I).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $email
 * @property string $intended_role
 * @property int $inviter_user_id
 * @property string $token_hash
 * @property string $status pending|accepted|expired|revoked
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read User $inviter
 */
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'email',
        'intended_role',
        'inviter_user_id',
        'token_hash',
        'status',
        'expires_at',
        'accepted_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Tenant proprietário do convite.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Usuário que enviou o convite.
     *
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }
}
