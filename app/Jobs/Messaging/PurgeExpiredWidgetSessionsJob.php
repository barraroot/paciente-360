<?php

namespace App\Jobs\Messaging;

use App\Domain\Messaging\Widget\Models\WebWidgetSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * T217 — Job para purgar sessões de widget expiradas e não identificadas.
 *
 * Executa diariamente às 03:00 BRT (ver console.php).
 *
 * Regra NC-10.c: sessões sem `identified_patient_id` expiram em 30 dias.
 * Após expiração, os dados provisórios (nome/telefone capturado no form pré-chat)
 * são deletados para minimizar retenção de dados pessoais (LGPD).
 *
 * Log estruturado: registra quantidade deletada por tenant para auditoria.
 */
class PurgeExpiredWidgetSessionsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $deletedCount = WebWidgetSession::whereNull('identified_patient_id')
            ->where('expires_at', '<', now())
            ->delete();

        Log::info('widget_sessions.purged', [
            'deleted_count' => $deletedCount,
            'purged_at' => now()->toIso8601String(),
        ]);
    }
}
