<?php

/*
|--------------------------------------------------------------------------
| Auditoria — Paciente360
|--------------------------------------------------------------------------
|
| Política de retenção do log de auditoria (Princípio I — LGPD,
| Princípio V — Observabilidade) e schedule expressions dos jobs que
| transitam registros entre tiers.
|
| Decisão FR-038 (sessão de clarificação 2026-05-10):
|
|   - Hot storage   (0–2 anos): tabela `audit_logs`, consultável e
|     exportável diretamente pelo painel do Admin Clínica
|     (FR-036/037).
|   - Cold storage  (2–5 anos): tabela `audit_logs_cold`, sem índices
|     pesados; recuperação por solicitação interna com SLA de 5 dias
|     úteis.
|   - Deleção física aos 5 anos: LGPD Art. 16 (minimização).
|
| O mínimo de 1 ano da constituição é coberto integralmente dentro do
| tier hot. Mudanças nesses valores exigem amendment.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Janela do tier hot (em dias)
    |--------------------------------------------------------------------------
    |
    | 730 dias = 2 anos. Após esse período o `ArchiveAuditLogsJob`
    | move o registro para `audit_logs_cold`.
    |
    */

    'hot_days' => (int) env('AUDIT_HOT_DAYS', 730),

    /*
    |--------------------------------------------------------------------------
    | Janela total até deleção (em dias)
    |--------------------------------------------------------------------------
    |
    | 1825 dias = 5 anos. Cobre tanto o tier cold (2–5 anos) quanto o
    | gatilho de deleção física: registros com `created_at < now() -
    | cold_days` são apagados de `audit_logs_cold` pelo
    | `DeleteExpiredAuditLogsJob`.
    |
    */

    'cold_days' => (int) env('AUDIT_COLD_DAYS', 1825),

    /*
    |--------------------------------------------------------------------------
    | Idade máxima absoluta do registro (em dias)
    |--------------------------------------------------------------------------
    |
    | Idade na qual o registro é deletado fisicamente. Igual a
    | `cold_days` por design (FR-038), mas mantido como chave separada
    | para que o job de deleção tenha um knob explícito caso futuras
    | políticas (ex.: tipos de evento com retenção diferente)
    | divergem.
    |
    */

    'delete_after_days' => (int) env('AUDIT_DELETE_AFTER_DAYS', 1825),

    /*
    |--------------------------------------------------------------------------
    | Tamanho do batch de arquivamento/deleção
    |--------------------------------------------------------------------------
    |
    | T264: jobs movem/deletam em batches transacionais para evitar
    | OOM e bloquear índices por longos períodos. 1000 é seguro para
    | a JSONB `payload` típica.
    |
    */

    'archive_batch_size' => (int) env('AUDIT_ARCHIVE_BATCH_SIZE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Schedule expressions (cron)
    |--------------------------------------------------------------------------
    |
    | T264 — jobs agendados mensalmente. Archive roda no dia 1 às 03:00
    | e delete no dia 1 às 04:00 para garantir ordenação: registros que
    | cruzam o limite de 5 anos são movidos para cold ANTES da deleção
    | física (defesa em profundidade contra perda acidental no
    | boundary).
    |
    */

    'schedule' => [
        'archive' => env('AUDIT_SCHEDULE_ARCHIVE', '0 3 1 * *'),
        'delete' => env('AUDIT_SCHEDULE_DELETE', '0 4 1 * *'),
    ],

];
