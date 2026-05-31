<?php

declare(strict_types=1);

namespace App\Console\Commands\Compliance;

use App\Domain\Privacy\Services\ConsentService;
use App\Jobs\Compliance\PurgeExpiredAudioRawJob;
use Illuminate\Console\Command;

/**
 * **T211 (Fase 18 — Polish, FR-055a)** — comando manual para disparar a
 * purga LGPD-aware dos áudios brutos expirados sem consent
 * `Transcricao`. Equivale ao cron diário 04:00 mas com gatilho ad-hoc
 * (operador rodando antes de auditoria, debug, etc.).
 *
 * `--tenant=ID` restringe a 1 tenant; sem o flag, varre todos.
 */
final class PurgeExtendedAudioCommand extends Command
{
    protected $signature = 'compliance:purge-extended-audio
        {--tenant= : Restringe a um tenant específico (ID numérico)}
        {--queue : Enfileira o job ao invés de executar inline}';

    protected $description = 'Apaga áudios brutos LGPD-aware (paciente sem consent transcricao + idade > retenção padrão).';

    public function handle(): int
    {
        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;

        $this->info(sprintf(
            'Disparando purga de áudio bruto%s.',
            $tenantId !== null ? " para tenant_id={$tenantId}" : ' para TODOS os tenants',
        ));

        if ($this->option('queue')) {
            PurgeExpiredAudioRawJob::dispatch($tenantId);
            $this->info('Job enfileirado na fila `privacy`.');

            return self::SUCCESS;
        }

        app(PurgeExpiredAudioRawJob::class, ['tenantId' => $tenantId])->handle(
            app(ConsentService::class),
        );
        $this->info('Purga concluída (inline). Veja audit_logs para o evento `compliance.audio_raw.purged`.');

        return self::SUCCESS;
    }
}
