<?php

declare(strict_types=1);

namespace Tests\Unit\Lgpd;

use App\Support\Lgpd\AnonymizationMap;
use Tests\TestCase;

/**
 * **T015** — Unit tests do {@see AnonymizationMap} (Phase 2 Foundational).
 *
 * Valida o mapa Q26 em isolamento: as 3 categorias estão corretamente
 * representadas, placeholders aceitam substituição de `{id}`, e campos
 * preservados carregam motivo legal + retention_days.
 */
class AnonymizationMapTest extends TestCase
{
    public function test_field_map_returns_at_least_one_field_per_category(): void
    {
        $map = AnonymizationMap::fieldMap();

        $categories = array_unique(array_column($map, 'category'));

        $this->assertContains(AnonymizationMap::CATEGORY_ANONYMIZE, $categories);
        $this->assertContains(AnonymizationMap::CATEGORY_DELETE, $categories);
        $this->assertContains(AnonymizationMap::CATEGORY_PRESERVE, $categories);
    }

    public function test_anonymized_fields_have_placeholder_key(): void
    {
        $anonymized = AnonymizationMap::fieldsByCategory(AnonymizationMap::CATEGORY_ANONYMIZE);

        $this->assertContains('nome', $anonymized);
        $this->assertContains('cpf', $anonymized);
        $this->assertContains('telefone', $anonymized);
        $this->assertContains('data_nascimento', $anonymized);
    }

    public function test_deleted_fields_include_storage_artifacts(): void
    {
        $deleted = AnonymizationMap::fieldsByCategory(AnonymizationMap::CATEGORY_DELETE);

        $this->assertContains('foto_url', $deleted, 'foto_url deve ser deletada (arquivo S3 + coluna).');
        $this->assertContains('anotacoes_livres', $deleted, 'Anotações são corpo livre — devem ser deletadas.');
        $this->assertContains('mensagens_corpo', $deleted, 'Corpo de mensagens deve ser deletado; metadados preservados.');
    }

    public function test_preserved_fields_carry_legal_reason_and_retention(): void
    {
        $map = AnonymizationMap::fieldMap();

        foreach ($map as $field => $config) {
            if ($config['category'] !== AnonymizationMap::CATEGORY_PRESERVE) {
                continue;
            }

            $this->assertArrayHasKey('reason', $config, "Campo preservado {$field} deve ter reason.");
            $this->assertArrayHasKey('retention_days', $config, "Campo preservado {$field} deve ter retention_days.");
            $this->assertGreaterThan(0, $config['retention_days']);
        }
    }

    public function test_controlled_prescriptions_preserved_for_two_years(): void
    {
        $map = AnonymizationMap::fieldMap();

        $this->assertSame(
            730,
            $map['prescricoes_controladas']['retention_days'] ?? null,
            'Portaria SVS/MS 344/98 exige 2 anos de retenção para receitas controladas.',
        );
        $this->assertSame('portaria_344_98', $map['prescricoes_controladas']['reason'] ?? null);
    }

    public function test_billing_records_preserved_for_five_years(): void
    {
        $map = AnonymizationMap::fieldMap();

        $this->assertSame(
            1825,
            $map['registros_financeiros']['retention_days'] ?? null,
            'Lei 12.682/2012 exige 5 anos de retenção para registros financeiros.',
        );
    }

    public function test_audit_logs_preserved_for_one_year_lgpd_art_16(): void
    {
        $map = AnonymizationMap::fieldMap();

        $this->assertSame(
            365,
            $map['audit_logs']['retention_days'] ?? null,
            'LGPD Art. 16 exige mínimo 1 ano de retenção para audit logs.',
        );
    }

    public function test_plan_replaces_id_placeholder_in_nome(): void
    {
        $plan = AnonymizationMap::plan(patientId: 123);

        $this->assertSame('Paciente Anonimizado #123', $plan['anonymize']['nome']);
    }

    public function test_plan_preserves_cpf_static_placeholder(): void
    {
        $plan = AnonymizationMap::plan(patientId: 999);

        $this->assertSame('000.000.000-00', $plan['anonymize']['cpf']);
    }

    public function test_plan_returns_arrays_in_three_categories(): void
    {
        $plan = AnonymizationMap::plan(patientId: 1);

        $this->assertArrayHasKey('anonymize', $plan);
        $this->assertArrayHasKey('delete', $plan);
        $this->assertArrayHasKey('preserve', $plan);

        $this->assertNotEmpty($plan['anonymize']);
        $this->assertNotEmpty($plan['delete']);
        $this->assertNotEmpty($plan['preserve']);
    }

    public function test_plan_preserve_entries_carry_full_metadata(): void
    {
        $plan = AnonymizationMap::plan(patientId: 1);

        foreach ($plan['preserve'] as $entry) {
            $this->assertArrayHasKey('field', $entry);
            $this->assertArrayHasKey('reason', $entry);
            $this->assertArrayHasKey('retention_days', $entry);
        }
    }
}
