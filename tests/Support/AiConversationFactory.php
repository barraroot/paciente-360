<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Helper de teste (feature 017) para montar conversas multi-turno.
 *
 * Cada turno é `['role' => 'patient'|'ai'|'user', 'body' => string]`, criado em
 * ordem com `created_at` crescente para a janela de histórico ser determinística.
 */
final class AiConversationFactory
{
    public static function whatsappChannel(Tenant $tenant): Channel
    {
        return Channel::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => 'whatsapp',
            'status' => 'ativo',
        ]);
    }

    public static function conversation(Tenant $tenant, ?Channel $channel = null): Conversation
    {
        return Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => ($channel ?? self::whatsappChannel($tenant))->id,
            'last_inbound_message_at' => now(),
            'ai_paused_until' => null,
        ]);
    }

    /**
     * Semeia uma sequência de turnos numa conversa.
     *
     * @param list<array{role: string, body: string}> $turns
     * @return Collection<int, Message>
     */
    public static function seedTurns(Tenant $tenant, Conversation $conversation, array $turns): Collection
    {
        $messages = collect();
        $base = now()->subMinutes(count($turns) + 1);

        foreach ($turns as $i => $turn) {
            $role = $turn['role'];
            $isInbound = $role === 'patient';

            $messages->push(Message::factory()->create([
                'tenant_id' => $tenant->id,
                'conversation_id' => $conversation->id,
                'direction' => $isInbound ? 'in' : 'out',
                'sender_type' => match ($role) {
                    'patient' => 'patient',
                    'ai' => 'ai',
                    default => 'user',
                },
                'body' => $turn['body'],
                'body_preview' => mb_substr($turn['body'], 0, 140),
                'status' => $isInbound ? 'delivered' : 'sent',
                'created_at' => (clone $base)->addMinutes($i),
                'sent_at' => (clone $base)->addMinutes($i),
            ]));
        }

        return $messages;
    }
}
