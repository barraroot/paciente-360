<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class AuditConfigTest extends TestCase
{
    public function test_hot_days_default_is_seven_hundred_thirty(): void
    {
        // FR-038 — tier "hot storage": 0–2 anos (730 dias). Período em
        // que o log é consultável/exportável diretamente pelo painel
        // do Admin Clínica.
        $this->assertSame(730, config('audit.hot_days'));
    }

    public function test_cold_days_default_is_one_thousand_eight_hundred_twenty_five(): void
    {
        // FR-038 — tier "cold storage": ciclo total de 5 anos
        // (1825 dias). Logs movidos do hot ficam arquivados aqui até
        // a deleção física.
        $this->assertSame(1825, config('audit.cold_days'));
    }

    public function test_delete_after_days_default_is_one_thousand_eight_hundred_twenty_five(): void
    {
        // FR-038 — deleção física aos 5 anos (1825 dias) em
        // conformidade com LGPD Art. 16 (minimização).
        $this->assertSame(1825, config('audit.delete_after_days'));
    }

    public function test_archive_schedule_is_a_valid_cron_expression(): void
    {
        // FR-038 / T264 — job ArchiveAuditLogsJob roda mensalmente.
        // Garantimos pelo menos 5 campos cron (min hora dia mês dow).
        $expression = config('audit.schedule.archive');

        $this->assertIsString($expression);
        $this->assertNotEmpty($expression);
        $this->assertGreaterThanOrEqual(
            5,
            count(preg_split('/\s+/', trim($expression))),
            'Expressão cron de arquivamento inválida.'
        );
    }

    public function test_delete_schedule_is_a_valid_cron_expression(): void
    {
        // FR-038 / T264 — job DeleteExpiredAuditLogsJob roda
        // mensalmente após o de arquivamento.
        $expression = config('audit.schedule.delete');

        $this->assertIsString($expression);
        $this->assertNotEmpty($expression);
        $this->assertGreaterThanOrEqual(
            5,
            count(preg_split('/\s+/', trim($expression))),
            'Expressão cron de deleção inválida.'
        );
    }

    public function test_archive_and_delete_schedules_are_different(): void
    {
        // Archive precisa rodar antes de delete no mesmo dia para que
        // registros que cruzam o limite de 5 anos sejam movidos para
        // cold antes da deleção física (defesa em profundidade contra
        // perda acidental no boundary).
        $this->assertNotSame(
            config('audit.schedule.archive'),
            config('audit.schedule.delete')
        );
    }

    public function test_hot_days_overridable_via_config(): void
    {
        // Princípio VII — valores de retenção podem ser ajustados em
        // ambientes específicos (ex.: testes E2E acelerados) sem
        // alterar código.
        config(['audit.hot_days' => 30]);

        $this->assertSame(30, config('audit.hot_days'));
    }

    public function test_archive_batch_size_default_is_positive_integer(): void
    {
        // T264 — jobs idempotentes em batches; batch_size protege
        // contra OOM ao mover milhões de linhas.
        $batchSize = config('audit.archive_batch_size');

        $this->assertIsInt($batchSize);
        $this->assertGreaterThan(0, $batchSize);
    }
}
