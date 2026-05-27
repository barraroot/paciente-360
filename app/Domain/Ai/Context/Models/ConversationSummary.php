<?php

declare(strict_types=1);

namespace App\Domain\Ai\Context\Models;

use App\Casts\AsJsonArray;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Ai\ConversationSummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Feature 017 (US1/US3) — resumo rolante compacto de uma conversa.
 *
 * Representa os turnos ANTERIORES à janela verbatim como fatos-chave + texto
 * comprimido, para a IA manter o fio sem reenviar todo o histórico (FR-002/022).
 * Sem PII bruta (gerado de mensagens já pseudonimizadas).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $conversation_id
 * @property string|null $summary_text
 * @property array<string, mixed>|null $key_facts
 * @property string|null $funnel_stage
 * @property int|null $covered_up_to_message_id
 * @property int $version
 */
class ConversationSummary extends Model
{
    /** @use HasFactory<ConversationSummaryFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'ai_conversation_summaries';

    protected static function newFactory(): ConversationSummaryFactory
    {
        return ConversationSummaryFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'summary_text',
        'key_facts',
        'funnel_stage',
        'covered_up_to_message_id',
        'version',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'key_facts' => AsJsonArray::class,
            'covered_up_to_message_id' => 'integer',
            'version' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
