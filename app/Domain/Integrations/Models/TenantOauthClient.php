<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * **T216 (Fase 8 — Lote D US-11.2)** — Client OAuth 2.0 (Q18).
 *
 * Gated — `OauthClientService` só instancia quando
 * `config('finalization.oauth_enabled') === true`.
 */
class TenantOauthClient extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'client_id',
        'client_secret_hash',
        'scopes',
        'is_active',
        'created_by_user_id',
        'last_used_at',
    ];

    protected $casts = [
        'scopes' => AsArrayObject::class,
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = ['client_secret_hash'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
