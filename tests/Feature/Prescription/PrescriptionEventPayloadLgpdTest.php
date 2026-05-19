<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\PrescriptionType;
use App\Events\Prescription\ReceitaProximaDoVencimento;
use App\Events\Prescription\ReceitaVencida;
use App\Support\Lgpd\ContainsNoClinicalData;
use Carbon\CarbonImmutable;
use ReflectionClass;
use Tests\TestCase;

/**
 * T087 — Gate LGPD: reflection valida 7 props exatas em ReceitaProximaDoVencimento.
 *
 * Este teste é gate constitucional (research §3). Qualquer adição de campo
 * clínico (medication_name, posology, notes, etc.) quebra este teste,
 * exigindo revisão LGPD explícita antes de prosseguir.
 */
class PrescriptionEventPayloadLgpdTest extends TestCase
{
    public function test_receita_proxima_do_vencimento_implements_contains_no_clinical_data(): void
    {
        $this->assertInstanceOf(
            ContainsNoClinicalData::class,
            new ReceitaProximaDoVencimento(
                prescriptionId: 1,
                patientId: 1,
                professionalId: 1,
                professionalName: 'Dr. Teste',
                daysUntilExpiry: 15,
                prescriptionType: PrescriptionType::Common,
                defaultAppointmentTypeId: null,
            ),
        );
    }

    public function test_receita_proxima_do_vencimento_has_exactly_seven_allowed_properties(): void
    {
        $reflection = new ReflectionClass(ReceitaProximaDoVencimento::class);
        $properties = collect($reflection->getProperties())
            ->map(fn ($p) => $p->getName())
            ->toArray();

        $allowedFields = [
            'prescriptionId',
            'patientId',
            'professionalId',
            'professionalName',
            'daysUntilExpiry',
            'prescriptionType',
            'defaultAppointmentTypeId',
        ];

        $this->assertEqualsCanonicalizing(
            $allowedFields,
            $properties,
            'Payload do evento mudou! Revisão LGPD obrigatória antes de adicionar campos. '
            .'NÃO inclua medication_name, posology, notes ou qualquer dado clínico.',
        );
    }

    public function test_receita_proxima_do_vencimento_has_no_clinical_fields(): void
    {
        $reflection = new ReflectionClass(ReceitaProximaDoVencimento::class);
        $properties = collect($reflection->getProperties())
            ->map(fn ($p) => $p->getName())
            ->toArray();

        $forbiddenFields = [
            'medication_name',
            'medicationName',
            'posology',
            'notes',
            'cpf',
            'phone',
            'address',
            'diagnosis',
            'clinicalData',
        ];

        foreach ($forbiddenFields as $field) {
            $this->assertNotContains(
                $field,
                $properties,
                "Campo clínico proibido '{$field}' encontrado em ReceitaProximaDoVencimento!",
            );
        }
    }

    public function test_receita_vencida_implements_contains_no_clinical_data(): void
    {
        $this->assertInstanceOf(
            ContainsNoClinicalData::class,
            new ReceitaVencida(
                prescriptionId: 1,
                patientId: 1,
                expiredAt: CarbonImmutable::today(),
            ),
        );
    }

    public function test_receita_vencida_has_no_clinical_fields(): void
    {
        $reflection = new ReflectionClass(ReceitaVencida::class);
        $properties = collect($reflection->getProperties())
            ->map(fn ($p) => $p->getName())
            ->toArray();

        $forbiddenFields = ['medication_name', 'medicationName', 'posology', 'notes'];

        foreach ($forbiddenFields as $field) {
            $this->assertNotContains(
                $field,
                $properties,
                "Campo clínico proibido '{$field}' encontrado em ReceitaVencida!",
            );
        }
    }

    public function test_all_properties_are_readonly(): void
    {
        $reflection = new ReflectionClass(ReceitaProximaDoVencimento::class);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                "Propriedade '{$property->getName()}' deve ser readonly.",
            );
        }
    }
}
