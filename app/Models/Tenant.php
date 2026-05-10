<?php

namespace App\Models;

use App\Casts\AsJsonArray;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;

/**
 * Model `Tenant` — raiz do multi-tenancy (Princípio II da constituição
 * v1.2.0). Identificação pública via `slug` (subdomínio); resolvido pelo
 * middleware `ResolveTenant` antes de qualquer Controller.
 *
 * Schema canônico em `specs/001-fundacao-multitenant/data-model.md` § 1.
 *
 * IMPORTANTE: este Model **não usa** a trait `BelongsToTenant`. Ele é o
 * próprio tenant; todas as queries internas que precisam buscar tenants
 * (ex.: middleware) consultam direto, sem global scope.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $cnpj
 * @property string $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $overdue_since
 * @property Carbon|null $restrictions_applied_at
 * @property int|null $plan_id
 * @property string|null $stripe_customer_id
 * @property string|null $subdomain_custom
 * @property Carbon $terms_accepted_at
 * @property string $terms_version
 * @property array<string, mixed> $onboarding_state
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use Billable, HasFactory, Notifiable, SoftDeletes;

    /**
     * O Cashier procura `stripe_id` por default; nossa tabela usa
     * `stripe_customer_id` (schema canonico em data-model.md § 1).
     */
    public function getStripeIdColumn(): string
    {
        return 'stripe_customer_id';
    }

    /**
     * Endereço de e-mail do tenant para notificações Cashier e
     * `Notifiable` (usa o responsável da conta como destinatário padrão).
     */
    public function routeNotificationForMail(): string
    {
        return $this->responsible_email;
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'cnpj',
        'responsible_name',
        'responsible_email',
        'responsible_phone',
        'status',
        'trial_ends_at',
        'overdue_since',
        'restrictions_applied_at',
        'plan_id',
        'stripe_customer_id',
        'subdomain_custom',
        'terms_accepted_at',
        'terms_version',
        'onboarding_state',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'overdue_since' => 'datetime',
            'restrictions_applied_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'onboarding_state' => AsJsonArray::class,
        ];
    }

    /**
     * Conveniência: tenant suspenso (acesso bloqueado).
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Plano comercial vigente (catálogo global).
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Usuários internos vinculados a este tenant.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Convites enviados para este tenant.
     *
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }
}
