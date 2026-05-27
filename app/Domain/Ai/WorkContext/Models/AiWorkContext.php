<?php

declare(strict_types=1);

namespace App\Domain\Ai\WorkContext\Models;

use App\Casts\AsJsonArray;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Ai\AiWorkContextFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature 017 (US2) — Contexto de Trabalho da clínica (um por tenant).
 *
 * Campos estruturados (serviços, preços, locais, política de sinal, tom,
 * perguntas de qualificação) + texto livre. Complementa a persona/RAG com a
 * precedência definida em FR-011. Sem conteúdo clínico.
 *
 * @property int $id
 * @property int $tenant_id
 * @property array<int, array<string, mixed>>|null $services
 * @property array<int, array<string, mixed>>|null $pricing
 * @property array<int, array<string, mixed>>|null $locations
 * @property array<string, mixed>|null $deposit_policy
 * @property string|null $tone
 * @property array<int, string>|null $qualification_questions
 * @property string|null $free_form
 * @property int $version
 * @property bool $is_active
 */
class AiWorkContext extends Model
{
    /** @use HasFactory<AiWorkContextFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'ai_work_contexts';

    protected static function newFactory(): AiWorkContextFactory
    {
        return AiWorkContextFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'services',
        'pricing',
        'locations',
        'deposit_policy',
        'tone',
        'qualification_questions',
        'free_form',
        'version',
        'is_active',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'services' => AsJsonArray::class,
            'pricing' => AsJsonArray::class,
            'locations' => AsJsonArray::class,
            'deposit_policy' => AsJsonArray::class,
            'qualification_questions' => AsJsonArray::class,
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
