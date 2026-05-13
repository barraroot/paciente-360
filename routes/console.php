<?php

use App\Jobs\Messaging\ExpireAiPausesJob;
use App\Jobs\Messaging\PurgeExpiredMediaJob;
use App\Jobs\Messaging\PurgeExpiredMessagesJob;
use App\Jobs\Messaging\PurgeExpiredWidgetSessionsJob;
use App\Jobs\Messaging\PurgeWebhookEventsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// T187 — Aplica restrições a tenants inadimplentes há > 7 dias (FR-014).
Schedule::command('tenants:apply-overdue-restrictions')->dailyAt('02:00');

// T245 — Remove convites expirados há mais de 30 dias (limpeza semanal).
Schedule::command('invitations:purge-expired')->weeklyOn(0, '03:00');

// T091 (Fase 4 Lote J) — Purga tokens Sanctum expirados/revogados > 90d.
// 03:00 BRT diário. withoutOverlapping previne execução paralela em catch-up.
Schedule::command('auth:tokens-purge-expired')
    ->dailyAt('03:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

// T264 — Retenção de auditoria (FR-038 — LGPD Art. 16).
// Archive roda no dia 1; delete-expired no dia 2 (sempre depois) para que
// registros no boundary entrem em cold ANTES da deleção física.
Schedule::command('audit:archive')->monthlyOn(1, '04:00');
Schedule::command('audit:delete-expired')->monthlyOn(2, '04:00');

// T261 — Purga snapshots de mesclagens antigas (Fase 2, Polish).
Schedule::command('app:purge-old-merge-snapshots')->monthlyOn(2, '04:00');

// T175 US-4.6 — Expiração de pausas de IA (cross-tenant). Roda a cada minuto.
// withoutOverlapping garante que execuções longas não se acumulam.
Schedule::job(new ExpireAiPausesJob)->everyMinute()->withoutOverlapping();

// T196 US-4.2 — Health check dos canais Instagram (Meta Graph API versão pinned).
// Research R7: detecta mismatch de versão ou depreciação da API.
Schedule::command('messaging:meta-health-check')->monthly();

// T217 US-4.3 — Purga sessões de widget expiradas e não identificadas (LGPD NC-10.c).
// Executa diariamente às 03:00 BRT (= 06:00 UTC).
Schedule::job(new PurgeExpiredWidgetSessionsJob)->dailyAt('06:45'); // 03:45 BRT = 06:45 UTC

// T257 — Purga conteúdo de mensagens expiradas (FR-039, 2 anos). Preserva metadata.
// 03:00 BRT = 06:00 UTC. withoutOverlapping previne paralelismo em tabelas grandes.
Schedule::job(new PurgeExpiredMessagesJob)->monthly()->at('06:00')->withoutOverlapping();

// T251 — Purga mídia expirada do S3 + seta media_purged_at (NC-14, 1 ano).
// 03:15 BRT = 06:15 UTC.
Schedule::job(new PurgeExpiredMediaJob)->monthly()->at('06:15')->withoutOverlapping();

// T258 — Hard delete de webhook events expirados (30 dias — log de infra).
// 03:30 BRT = 06:30 UTC.
Schedule::job(new PurgeWebhookEventsJob)->monthly()->at('06:30')->withoutOverlapping();

// T270 — Métricas Prometheus: tamanho das filas de messaging (a cada minuto).
// withoutOverlapping evita acúmulo se Redis estiver lento.
Schedule::command('messaging:metrics-queue-sizes')->everyMinute()->withoutOverlapping();

// T270 — Métricas Prometheus: conversas ativas por (tenant, canal) (a cada 5min).
// Agrega cross-tenant via query SQL; sem withoutOverlapping (query rápida, < 50ms).
Schedule::command('messaging:metrics-conversations')->everyFiveMinutes();
