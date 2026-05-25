<?php

namespace App\Console\Commands\Channels;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Channel\Services\EvolutionInstanceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Feature 014 (US3) — Reconcilia o estado real dos canais Evolution.
 *
 * Cobre a perda silenciosa de sessão: se o webhook `connection.update` não
 * chegar, este cron consulta o servidor e atualiza o `status` do canal,
 * garantindo refletir quedas em ≤ 1 min (SC-005).
 */
#[Signature('channels:reconcile-evolution-state')]
#[Description('Reconcilia o status de conexão dos canais Evolution (WhatsApp não oficial).')]
class ReconcileEvolutionStateCommand extends Command
{
    public function handle(EvolutionInstanceService $instances): int
    {
        $channels = Channel::withoutGlobalScopes()
            ->where('provider', 'evolution')
            ->whereIn('status', ['ativo', 'conectando'])
            ->get();

        $reconciled = 0;

        foreach ($channels as $channel) {
            try {
                $status = $instances->refreshState($channel);
                $reconciled++;
                $this->line("channel {$channel->id}: {$status}");
            } catch (Throwable $e) {
                $this->warn("channel {$channel->id}: falha ao reconciliar — {$e->getMessage()}");
            }
        }

        $this->info("Reconciliados {$reconciled}/{$channels->count()} canais Evolution.");

        return self::SUCCESS;
    }
}
