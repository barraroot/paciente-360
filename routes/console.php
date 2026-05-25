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

// Feature 014 US-3 — Reconcilia estado dos canais Evolution (fallback do webhook
// connection.update). Reflete quedas de sessão em ≤ 1 min (SC-005).
Schedule::command('channels:reconcile-evolution-state')->everyMinute()->withoutOverlapping();

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

/*
|--------------------------------------------------------------------------
| Fase 5 — Agenda de Consultas (T029)
|--------------------------------------------------------------------------
|
| 6 schedule entries cobrindo:
|  - Cleanup de slot reservations expiradas (clarify nº 2)
|  - Expiração de notificações de lista de espera + notify next FIFO (clarify nº 8)
|  - Dispatch de confirmações T-24h/T-2h/retry T-30min (clarify nº 6)
|  - Auto-close de consultas sem marcação após 7 dias (clarify nº 14)
|  - Polling fallback Google Calendar (clarify nº 10 / R3)
|  - Renovação de watch channels Google antes do TTL (R3)
|
| Commands criados nas tasks T085 (cleanup), T133 (expire waitlist),
| T108 (dispatch confirmações), T109 (auto-close), T159 (poll), T160 (renew).
*/

Schedule::command('agenda:cleanup-expired-reservations')->everyMinute()->onOneServer();

Schedule::command('agenda:expire-waitlist-notifications')->everyMinute()->onOneServer();

Schedule::command('agenda:dispatch-confirmations')->everyFiveMinutes()->onOneServer();

Schedule::command('agenda:auto-close-stale-appointments')
    ->dailyAt('00:30')
    ->timezone('America/Sao_Paulo')
    ->onOneServer();

Schedule::command('agenda:google-poll-fallback')->everyFiveMinutes()->onOneServer();

Schedule::command('agenda:google-renew-watch-channels')
    ->dailyAt('02:00')
    ->timezone('America/Sao_Paulo')
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Fase 7 — Gestão de Receituários (T107)
|--------------------------------------------------------------------------
|
| prescriptions:process-alerts  — D-15/D-7/D-1 checkpoints (06:00 BRT).
|   withoutOverlapping: evita processamento paralelo em tenants com muitas receitas.
|
| prescriptions:expire-active   — expiração de receitas ativas (00:30 BRT).
|   Roda antes do cron de alertas para garantir que receitas superseded
|   não recebam alertas desnecessários.
*/

Schedule::command('prescriptions:process-alerts')
    ->dailyAt('06:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

Schedule::command('prescriptions:expire-active')
    ->dailyAt('00:30')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

// T173 — Purge de versões antigas de PDFs (não-controladas, retém últimas 5).
//   Roda semanalmente às segundas 02:00 BRT.
Schedule::command('prescriptions:purge-old-pdfs')
    ->weeklyOn(1, '02:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Fase 8 — Finalização do MVP (T009)
|--------------------------------------------------------------------------
|
| 8 schedules placeholder. Commands criados nos lotes correspondentes:
|   Lote A — Privacidade (T057, T058, T075)
|   Lote B — Super Admin (T104, T135)
|   Lote C — Campanhas (T172)
|   Lote D — Integrações (T202)
|   Lote E — Relatórios (T252)
|
| Todos com withoutOverlapping() para evitar paralelismo em catch-up.
| Timezone America/Sao_Paulo onde horários são sensíveis a fuso (notificações).
|
*/

// Lote A — Auditoria semanal de pseudonimização (Q29 — runtime replay).
Schedule::command('privacy:audit-pseudonymization-weekly')
    ->weeklyOn(1, '04:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

// Lote A — Notificações progressivas LGPD (Q27 — D-5 inbox, D-2 inbox+e-mail+alerta).
Schedule::command('privacy:notify-deadlines')
    ->dailyAt('09:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

// Lote A US-13.2 (T058) — Marca solicitações com deadline vencido (AC-13.2.7).
Schedule::command('privacy:mark-expired')
    ->dailyAt('00:30')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

// Lote B — Métricas globais (MRR, ARR, churn, conversão, consumo IA).
Schedule::command('super-admin:compute-global-metrics')
    ->hourly()
    ->withoutOverlapping();

// Lote B — Detecção de anomalias (Q22 — 4 categorias).
Schedule::command('super-admin:detect-anomalies')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// Lote B — Aplicação da política de retenção pós-cancelamento (Q20).
Schedule::command('super-admin:apply-retention-policy')
    ->dailyAt('02:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

// Lote C — Dispatch de campanhas agendadas (US-9.2).
Schedule::command('campaigns:dispatch-scheduled')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Lote D — Purge de Dead Letter Queue expirada (Q16 — 30d retention).
Schedule::command('integrations:purge-expired-dlq')
    ->dailyAt('03:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

// Lote E — Agregação horária de KPIs (Q9 — janelas ≥7d).
Schedule::command('reports:aggregate-hourly')
    ->hourlyAt(5)
    ->withoutOverlapping();
