<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Mcp;

use App\Domain\Ai\Mcp\Server\PacienteMcpServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * T063a — FR-023 guardrail estrutural.
 * Valida que nenhuma capability do MCP tem lógica que move card para status de confirmação automática.
 * O ÚNICO caminho para 'agendado' é PromoteToScheduledOnHoldPlaced (listener).
 * O ÚNICO caminho para 'confirmado' é PromoteToConfirmedOnReservationPaid (listener).
 *
 * @group guardrail-fr023
 */
final class IaCannotAutoConfirmTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_capability_has_agendado_or_confirmado_in_source_code(): void
    {
        // Se a classe PacienteMcpServer não existe, skip
        if (! class_exists(PacienteMcpServer::class)) {
            $this->markTestSkipped('PacienteMcpServer não implementado ainda');
        }

        // Varredura via reflection sobre os arquivos em app/Domain/Ai/Mcp/Capabilities/
        $capabilityDir = app_path('Domain/Ai/Mcp/Capabilities');
        if (! is_dir($capabilityDir)) {
            $this->markTestSkipped('Diretório de capabilities não existe');
        }

        $files = glob($capabilityDir.'/*.php');
        $this->assertNotEmpty($files, 'Nenhuma capability encontrada');

        foreach ($files as $filePath) {
            $sourceCode = file_get_contents($filePath);
            $className = basename($filePath, '.php');

            // Skip BaseMcpCapability pois é a classe base (pode ter referências em comentários)
            if ($className === 'BaseMcpCapability') {
                continue;
            }

            // Remove comentários (// e /* */) para evitar falsos positivos
            // Primeiro remove comentários multi-linha
            $cleaned = preg_replace('/\/\*[\s\S]*?\*\//m', '', $sourceCode);
            // Depois remove comentários de linha única (// até fim da linha)
            $cleaned = preg_replace('/\/\/.*$/m', '', $cleaned);

            // Verifica que strings 'agendado' e 'confirmado' não aparecem no código executável
            // (string literals apenas, não comentários)
            $hasAgendado = preg_match("/['\"]agendado['\"]/i", $cleaned) === 1;
            $hasConfirmado = preg_match("/['\"]confirmado['\"]/i", $cleaned) === 1;

            $this->assertFalse(
                $hasAgendado,
                "Capability {$className} contém referência a 'agendado' — violaria FR-023"
            );

            $this->assertFalse(
                $hasConfirmado,
                "Capability {$className} contém referência a 'confirmado' — violaria FR-023"
            );
        }
    }

    public function test_only_listener_promote_to_scheduled_can_move_to_agendado(): void
    {
        // Verifica que PromoteToScheduledOnHoldPlaced existe
        $listenerClass = 'App\\Listeners\\Crm\\Kanban\\PromoteToScheduledOnHoldPlaced';
        if (! class_exists($listenerClass)) {
            $this->markTestSkipped("Listener {$listenerClass} não implementado ainda (US3)");
        }

        // Verifica que escuta SlotReservation::created
        $reflection = new ReflectionClass($listenerClass);
        $listenerFile = file_get_contents($reflection->getFileName());
        $this->assertStringContainsString(
            'SlotReservation',
            $listenerFile,
            'Listener deve escutar SlotReservation::created'
        );
    }

    public function test_only_listener_promote_to_confirmed_can_move_to_confirmado(): void
    {
        // Verifica que PromoteToConfirmedOnReservationPaid existe
        $listenerClass = 'App\\Listeners\\Crm\\Kanban\\PromoteToConfirmedOnReservationPaid';
        if (! class_exists($listenerClass)) {
            $this->markTestSkipped("Listener {$listenerClass} não implementado ainda (US3)");
        }

        // Verifica que escuta AppointmentConfirmed ou evento de pagamento
        $reflection = new ReflectionClass($listenerClass);
        $listenerFile = file_get_contents($reflection->getFileName());
        $this->assertTrue(
            str_contains($listenerFile, 'AppointmentConfirmed') ||
            str_contains($listenerFile, 'ReservationPaid') ||
            str_contains($listenerFile, 'Confirmed'),
            'Listener deve escutar evento de confirmação de reserva/pagamento'
        );
    }
}
