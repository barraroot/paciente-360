<?php

namespace App\Filament\Widgets;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * T281 — Widget para Super Admin: métricas agregadas de mensageria por tenant.
 *
 * **IMPORTANTE — Princípio I (LGPD)**: Widget exibe APENAS contadores agregados.
 * Nunca expõe conteúdo de mensagens (body), dados pessoais de pacientes,
 * ou qualquer informação identificável.
 *
 * Dados exibidos:
 *  - Quantos tenants têm canais conectados
 *  - Total de canais ativos
 *  - Quantas conversas (todas) em execução
 *  - Quantas mensagens (todas) trocadas
 *
 * Cache: 5 minutos para evitar queries pesadas em toda refresh.
 * Visibilidade: APENAS Super Admin (guard `super-admin`, não tenant admins).
 *
 * Localização: Dashboard `/admin` → adicionado via DashboardPage::getHeaderWidgets().
 */
class TenantMessagingStatsWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $tenantsWithChannels = Cache::remember(
            'super_admin.widget.tenants_with_channels',
            300,
            function (): int {
                return Channel::withoutGlobalScopes()
                    ->distinct('tenant_id')
                    ->count('tenant_id');
            }
        );

        $activeChannels = Cache::remember(
            'super_admin.widget.active_channels',
            300,
            function (): int {
                return Channel::withoutGlobalScopes()
                    ->where('status', 'ativo')
                    ->count();
            }
        );

        $totalConversations = Cache::remember(
            'super_admin.widget.total_conversations',
            300,
            function (): int {
                return Conversation::withoutGlobalScopes()
                    ->count();
            }
        );

        $totalMessages = Cache::remember(
            'super_admin.widget.total_messages',
            300,
            function (): int {
                return Message::withoutGlobalScopes()
                    ->count();
            }
        );

        return [
            Stat::make('Tenants com canais', $tenantsWithChannels)
                ->color('info')
                ->icon('heroicon-o-building-office-2'),

            Stat::make('Canais ativos', $activeChannels)
                ->color('success')
                ->icon('heroicon-o-link'),

            Stat::make('Conversas totais', $totalConversations)
                ->color('primary')
                ->icon('heroicon-o-chat-bubble-left-right'),

            Stat::make('Mensagens totais', $totalMessages)
                ->color('warning')
                ->icon('heroicon-o-envelope'),
        ];
    }
}
