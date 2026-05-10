<?php

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

// T264 — Retenção de auditoria (FR-038 — LGPD Art. 16).
// Archive roda no dia 1; delete-expired no dia 2 (sempre depois) para que
// registros no boundary entrem em cold ANTES da deleção física.
Schedule::command('audit:archive')->monthlyOn(1, '04:00');
Schedule::command('audit:delete-expired')->monthlyOn(2, '04:00');
