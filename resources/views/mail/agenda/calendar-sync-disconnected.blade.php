@component('mail::message')
# Sincronização do Google Calendar interrompida

Olá {{ $professionalName ?? 'Doutor(a)' }},

A sincronização entre o Paciente360 ({{ $tenantName }}) e o seu Google Calendar
foi **interrompida**.

Isso pode ter acontecido porque você revogou o acesso ou a sessão expirou.

Suas consultas existentes no Google Calendar **permanecem inalteradas**, mas
novas mudanças no CRM não estão sendo sincronizadas.

@component('mail::button', ['url' => $reconnectUrl])
Reconectar Google Calendar
@endcomponent

Se foi você quem desconectou, pode ignorar este email.

Equipe Paciente360
@endcomponent
