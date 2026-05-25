<?php

namespace Tests\Feature\Notifications;

use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Notification\Services\NotificationTemplateResolver;

/**
 * Gate G3 (Princípio I) — nenhuma variável/corpo carrega dado clínico;
 * allow-list de `variables_map` enforçada.
 */
class OutboundPayloadLgpdTest extends OutboundNotificationTestCase
{
    public function test_variables_allow_list_rejects_clinical_keys(): void
    {
        $this->assertFalse(NotificationTemplateResolver::variablesAreAllowed([
            'medication_name' => 'x',
        ]));
        $this->assertFalse(NotificationTemplateResolver::variablesAreAllowed([
            'posology' => 'x',
        ]));
        $this->assertFalse(NotificationTemplateResolver::variablesAreAllowed([
            'diagnosis' => 'x',
        ]));

        $this->assertTrue(NotificationTemplateResolver::variablesAreAllowed([
            'patient_name' => 'patient_name',
            'appointment_datetime' => 'appointment_datetime',
        ]));
    }

    public function test_sent_message_carries_only_non_clinical_variables(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-lgpd', 'medico');
        $channel = $this->makeWhatsAppChannel($tenant);
        $this->makeTemplate($tenant);
        $this->makeApprovedChannelTemplate($tenant, $channel);
        $patient = $this->makePatient($tenant);

        // Contexto inclui chave clínica que NÃO está na allow-list — deve ser filtrada.
        $request = $this->confirmationRequest($tenant, $patient);
        $this->dispatcher()->dispatch($request);

        /** @var Message $message */
        $message = Message::withoutGlobalScopes()->where('content_type', 'template')->firstOrFail();

        $variableKeys = array_keys($message->template_variables ?? []);
        foreach ($variableKeys as $key) {
            $this->assertContains($key, NotificationTemplateResolver::ALLOWED_VARIABLES);
        }

        $serialized = json_encode($message->template_variables);
        foreach (['medicament', 'posolog', 'diagn', 'cirurgia'] as $clinical) {
            $this->assertStringNotContainsStringIgnoringCase($clinical, (string) $serialized);
        }
    }
}
