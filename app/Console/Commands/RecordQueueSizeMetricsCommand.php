<?php

namespace App\Console\Commands;

use App\Support\Metrics\MessagingMetricsContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

/**
 * T270 — Coleta o tamanho das filas de messaging e publica nas métricas Prometheus.
 *
 * Roda a cada minuto via scheduler (routes/console.php). Para cada fila
 * monitorada, obtém o número de jobs pendentes via Queue::size() e atualiza
 * o gauge `paciente360_queue_size{queue}`.
 *
 * Queue::size() é O(1) no Redis (LLEN). As 5 filas são consultadas em sequência
 * — total < 5ms por execução normal. `withoutOverlapping` no scheduler garante
 * que execuções lentas (Redis lento) não se acumulem.
 *
 * Filas monitoradas (Horizon supervisors — Princípio V / research R7):
 *  - webhooks-meta
 *  - outbound-messages
 *  - media-processing
 *  - reverb
 *  - default
 *
 * @see routes/console.php
 */
final class RecordQueueSizeMetricsCommand extends Command
{
    protected $signature = 'messaging:metrics-queue-sizes';

    protected $description = 'Registra o tamanho das filas de messaging no gauge Prometheus.';

    /**
     * Filas de messaging monitoradas (Horizon supervisors — research R7).
     *
     * @var list<string>
     */
    private const QUEUES = [
        'webhooks-meta',
        'outbound-messages',
        'media-processing',
        'reverb',
        'default',
    ];

    public function handle(MessagingMetricsContract $metrics): int
    {
        foreach (self::QUEUES as $queue) {
            try {
                $size = Queue::size($queue);
                $metrics->queueSize($queue, $size);
            } catch (\Throwable $e) {
                // Fila pode não existir ainda — logar e continuar
                $this->warn("Fila '{$queue}' indisponível: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
