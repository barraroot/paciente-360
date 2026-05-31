<?php

namespace App\Providers;

use App\Domain\Ai\Mcp\CircuitBreaker\Events\McpCircuitClosed;
use App\Domain\Ai\Mcp\CircuitBreaker\Events\McpCircuitOpened;
use App\Domain\Integrations\Listeners\BroadcastDomainEventToWebhooksListener;
use App\Domain\Messaging\Infrastructure\Listeners\AnonimizaMensagensOnPacienteAnonimizadoListener;
use App\Domain\Messaging\Infrastructure\Listeners\SetAiPausedOnOutboundMessageListener;
use App\Domain\Messaging\Message\Events\MensagemEnviada;
use App\Events\Contracts\Auditable;
use App\Events\Paciente\PacienteAnonimizado;
use App\Events\Professional\ProfessionalDeactivated;
use App\Listeners\Ai\Mcp\PersistMcpCircuitSnapshotListener;
use App\Listeners\Crm\Kanban\PromoteToScheduledOnHoldPlaced;
use App\Listeners\Paciente\RegistraEventoTimelineListener;
use App\Listeners\PersistAuditLogListener;
use App\Listeners\Professional\ProfessionalDeactivatedListener;
use App\Models\Agenda\SlotReservation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Provider de eventos da aplicação.
 *
 * Registra o `PersistAuditLogListener` para interceptar QUALQUER evento
 * que implemente a interface `Auditable` — padrão "interface wildcard"
 * do Laravel 13 via `Event::listen(InterfaceClass::class, ...)`.
 *
 * @see App\Events\Contracts\Auditable
 * @see App\Listeners\PersistAuditLogListener
 * @see specs/001-fundacao-multitenant/spec.md — FR-035
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap dos listeners de eventos.
     */
    public function boot(): void
    {
        Event::listen(Auditable::class, PersistAuditLogListener::class);

        // Fase 2 (T035) — projeção de eventos auditáveis em `eventos_timeline`
        // para a timeline do paciente. Coexiste com o listener de audit_logs:
        // ambos são chamados em sequência pelo dispatcher do Laravel.
        Event::listen(Auditable::class, RegistraEventoTimelineListener::class);

        // T260 — listener específico para o evento ProfessionalDeactivated.
        // Cria TarefaReatribuicao e dispara ReassignOrphansJob.
        Event::listen(ProfessionalDeactivated::class, ProfessionalDeactivatedListener::class);

        // T173 US-4.6 — Pausa implícita da IA quando um humano envia mensagem outbound.
        // Filtra sender_type === 'user'; ignora 'system' e 'ai'.
        Event::listen(MensagemEnviada::class, SetAiPausedOnOutboundMessageListener::class);

        // T255 — Anonimização de mensagens ao anonimizar paciente (LGPD FR-018, NC-14.b).
        // Mensagens recebidas têm conteúdo zerado; enviadas são preservadas.
        Event::listen(PacienteAnonimizado::class, AnonimizaMensagensOnPacienteAnonimizadoListener::class);

        // T193 (Fase 8 — Lote D US-11.1) — Bridge eventos de domínio → webhooks.
        // Subscriber registra 13 eventos do catálogo Q17 individualmente.
        Event::subscribe(BroadcastDomainEventToWebhooksListener::class);

        // Feature 018 (T054, US7, FR-053d) — persiste transições do circuit
        // breaker do MCP em `mcp_circuit_breaker_snapshots` para
        // analytics/auditoria distinguível (automatic vs manual_flag).
        Event::listen(
            McpCircuitOpened::class,
            [PersistMcpCircuitSnapshotListener::class, 'handleOpened'],
        );
        Event::listen(
            McpCircuitClosed::class,
            [PersistMcpCircuitSnapshotListener::class, 'handleClosed'],
        );

        // Feature 018 (T101, US3, FR-018) — quando a IA coloca um hold
        // tentativo (SlotReservation criado com holder_type='ia'), promove
        // o card do paciente para 'agendado'. Usa eloquent event direto
        // para NÃO modificar SlotReservationService da Fase 5.
        Event::listen(
            'eloquent.created: '.SlotReservation::class,
            PromoteToScheduledOnHoldPlaced::class,
        );
    }
}
