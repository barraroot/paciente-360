<?php

namespace Database\Factories\Ai;

use App\Domain\Ai\Context\Models\ConversationSummary;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationSummary>
 */
class ConversationSummaryFactory extends Factory
{
    protected $model = ConversationSummary::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'conversation_id' => Conversation::factory(),
            'summary_text' => 'Paciente relatou enxaqueca frequente; já demonstrou interesse na consulta.',
            'key_facts' => [
                'complaint' => 'enxaqueca',
                'qualification' => ['frequência: quase diária'],
                'intent' => 'agendamento',
            ],
            'funnel_stage' => 'qualifying',
            'covered_up_to_message_id' => null,
            'version' => 1,
        ];
    }
}
