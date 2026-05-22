<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Listeners;

use App\Domain\Campaigns\Events\CampanhaDisparada;
use App\Domain\Integrations\Services\WebhookDispatcher;
use App\Domain\Messaging\Message\Events\MensagemEnviada;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Privacy\Events\ConsentimentoRegistrado;
use App\Domain\Privacy\Events\ConsentimentoRevogado;
use App\Events\Agenda\ConsultaCancelada;
use App\Events\Agenda\ConsultaConfirmada;
use App\Events\Agenda\ConsultaCriada;
use App\Events\Agenda\ConsultaReagendada;
use App\Events\Paciente\PacienteAtualizado;
use App\Events\Paciente\PacienteCriado;
use App\Events\Prescription\PrescricaoCriada;
use App\Events\Prescription\ReceitaRenovada;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Str;

/**
 * **T193 (Fase 8 — Lote D US-11.1)** — Bridge: eventos de domínio → webhooks.
 *
 * Subscriber estilo Laravel — método `subscribe(Dispatcher $events)` registra
 * 13 eventos do catálogo Q17. Para cada evento recebido, mapeia para o
 * `event_type` público (snake_case dot-notation) e despacha via
 * `WebhookDispatcher`.
 *
 * **NÃO usa Event Discovery** — Laravel 11+ não descobre subscribers, eles
 * precisam de registro explícito em `EventServiceProvider::$subscribe`.
 */
final class BroadcastDomainEventToWebhooksListener
{
    public function __construct(
        private readonly WebhookDispatcher $dispatcher,
    ) {}

    /**
     * Mapa Q17: classe de evento → event_type público.
     *
     * @var array<class-string, string>
     */
    private const EVENT_CATALOG = [
        // Agenda (4)
        ConsultaCriada::class => 'agendamento.criado',
        ConsultaConfirmada::class => 'agendamento.confirmado',
        ConsultaCancelada::class => 'agendamento.cancelado',
        ConsultaReagendada::class => 'agendamento.reagendado',

        // Pacientes (2)
        PacienteCriado::class => 'paciente.criado',
        PacienteAtualizado::class => 'paciente.atualizado',

        // Messaging (2)
        MensagemRecebida::class => 'mensagem.recebida',
        MensagemEnviada::class => 'mensagem.enviada',

        // Prescrições (2) — controladas são mascaradas no dispatcher
        PrescricaoCriada::class => 'prescricao.criada',
        ReceitaRenovada::class => 'prescricao.renovada',

        // Campanhas (1)
        CampanhaDisparada::class => 'campanha.disparada',

        // Privacidade (2)
        ConsentimentoRegistrado::class => 'consentimento.registrado',
        ConsentimentoRevogado::class => 'consentimento.revogado',
    ];

    public function subscribe(Dispatcher $events): void
    {
        foreach (array_keys(self::EVENT_CATALOG) as $eventClass) {
            $events->listen($eventClass, [self::class, 'handle']);
        }
    }

    public function handle(object $event): void
    {
        $eventType = self::EVENT_CATALOG[$event::class] ?? null;
        if ($eventType === null) {
            return;
        }

        $tenantId = $this->resolveTenantId($event);
        if ($tenantId === null) {
            return;
        }

        $eventId = (string) Str::uuid();
        $payload = $this->buildPayload($event);

        $this->dispatcher->dispatch($eventType, $eventId, $payload, $tenantId);
    }

    private function resolveTenantId(object $event): ?int
    {
        if (property_exists($event, 'tenantId') && isset($event->tenantId)) {
            return (int) $event->tenantId;
        }

        $resolved = app()->bound('tenant') ? app('tenant') : null;

        return $resolved?->id;
    }

    /**
     * Constrói payload genérico via reflection — usa apenas props públicas.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(object $event): array
    {
        $ref = new \ReflectionObject($event);
        $payload = [];

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            if (in_array($name, ['signature', 'socket'], true)) {
                continue;
            }

            $value = $prop->getValue($event);

            // Normaliza Carbon/DateTime → ISO 8601.
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format(\DateTimeInterface::ATOM);
            }

            $payload[Str::snake($name)] = $value;
        }

        return $payload;
    }
}
