<?php

namespace App\Providers;

use App\Domain\Messaging\Infrastructure\Listeners\AnonimizaMensagensOnPacienteAnonimizadoListener;
use App\Domain\Messaging\Infrastructure\Listeners\SetAiPausedOnOutboundMessageListener;
use App\Domain\Messaging\Message\Events\MensagemEnviada;
use App\Events\Contracts\Auditable;
use App\Events\Paciente\PacienteAnonimizado;
use App\Events\Professional\ProfessionalDeactivated;
use App\Listeners\Paciente\RegistraEventoTimelineListener;
use App\Listeners\PersistAuditLogListener;
use App\Listeners\Professional\ProfessionalDeactivatedListener;
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
    }
}
