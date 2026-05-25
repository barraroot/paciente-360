<?php

use App\Http\Controllers\Api\V1\Agenda\AppointmentController;
use App\Http\Controllers\Api\V1\Agenda\AppointmentTypeController;
use App\Http\Controllers\Api\V1\Agenda\ProfessionalScheduleController;
use App\Http\Controllers\Api\V1\Agenda\ScheduleExceptionController;
use App\Http\Controllers\Api\V1\Agenda\SlotController;
use App\Http\Controllers\Api\V1\Agenda\WaitlistController;
use App\Http\Controllers\Api\V1\Audit\AuditLogsController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutAllController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\TokensController;
use App\Http\Controllers\Api\V1\Billing\AiUsageController;
use App\Http\Controllers\Api\V1\Billing\CheckoutController;
use App\Http\Controllers\Api\V1\Billing\PlansController;
use App\Http\Controllers\Api\V1\Billing\SubscriptionController;
use App\Http\Controllers\Api\V1\Campaigns\CampaignsController;
use App\Http\Controllers\Api\V1\Convenios\ConveniosController;
use App\Http\Controllers\Api\V1\Inbox\AssignmentRulesController;
use App\Http\Controllers\Api\V1\Inbox\AssignmentsController;
use App\Http\Controllers\Api\V1\Inbox\ChannelsController;
use App\Http\Controllers\Api\V1\Inbox\ChannelTemplatesController;
use App\Http\Controllers\Api\V1\Inbox\ConversationsController;
use App\Http\Controllers\Api\V1\Inbox\InboxPollController;
use App\Http\Controllers\Api\V1\Inbox\MediaController;
use App\Http\Controllers\Api\V1\Inbox\MessagesController;
use App\Http\Controllers\Api\V1\Inbox\PresenceController;
use App\Http\Controllers\Api\V1\Inbox\QuickRepliesController;
use App\Http\Controllers\Api\V1\Inbox\TakeoverController;
use App\Http\Controllers\Api\V1\Integrations\ApiTokensController;
use App\Http\Controllers\Api\V1\Integrations\WebhooksController;
use App\Http\Controllers\Api\V1\Notifications\NotificationTemplateController;
use App\Http\Controllers\Api\V1\Onboarding\OnboardingController;
use App\Http\Controllers\Api\V1\Pacientes\AnotacoesController;
use App\Http\Controllers\Api\V1\Pacientes\ExportacaoController;
use App\Http\Controllers\Api\V1\Pacientes\FunilController;
use App\Http\Controllers\Api\V1\Pacientes\ImportacaoController;
use App\Http\Controllers\Api\V1\Pacientes\MesclagemController;
use App\Http\Controllers\Api\V1\Pacientes\PacientesController;
use App\Http\Controllers\Api\V1\Pacientes\PacienteTagsController;
use App\Http\Controllers\Api\V1\Pacientes\PatchStatusController;
use App\Http\Controllers\Api\V1\Pacientes\TagsController;
use App\Http\Controllers\Api\V1\Pacientes\TimelineController;
use App\Http\Controllers\Api\V1\Panel\PanelHomeController;
use App\Http\Controllers\Api\V1\Prescriptions\PrescriptionAlertConfigController;
use App\Http\Controllers\Api\V1\Prescriptions\PrescriptionContextForAiController;
use App\Http\Controllers\Api\V1\Prescriptions\PrescriptionController;
use App\Http\Controllers\Api\V1\Prescriptions\PrescriptionCsvExportController;
use App\Http\Controllers\Api\V1\Prescriptions\PrescriptionPdfController;
use App\Http\Controllers\Api\V1\Prescriptions\PrescriptionRenewalController;
use App\Http\Controllers\Api\V1\Prescriptions\PrescriptionReportController;
use App\Http\Controllers\Api\V1\Privacy\ConsentsController;
use App\Http\Controllers\Api\V1\Privacy\ForgettingController;
use App\Http\Controllers\Api\V1\Privacy\PortabilityController;
use App\Http\Controllers\Api\V1\Professionals\CheckEmailController;
use App\Http\Controllers\Api\V1\Professionals\EspecialidadesAutocompleteController;
use App\Http\Controllers\Api\V1\Professionals\ProfessionalActivateController;
use App\Http\Controllers\Api\V1\Professionals\ProfessionalsController;
use App\Http\Controllers\Api\V1\Reports\ClinicalReportController;
use App\Http\Controllers\Api\V1\Reports\ExecutiveDashboardController;
use App\Http\Controllers\Api\V1\Reports\OperationalReportController;
use App\Http\Controllers\Api\V1\Tenant\CurrentTenantController;
use App\Http\Controllers\Api\V1\Tenant\RegisterController as TenantRegisterController;
use App\Http\Controllers\Api\V1\Users\InvitationsController;
use App\Http\Controllers\Api\V1\Users\UsersController;
use App\Http\Controllers\Webhooks\MetaInstagramWebhookController;
use App\Http\Controllers\Webhooks\TwilioStatusCallbackController;
use App\Http\Controllers\Webhooks\TwilioWhatsAppWebhookController;
use App\Http\Controllers\Widget\WidgetConfigController;
use App\Http\Middleware\ValidateMetaSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — `/api/v1`
|--------------------------------------------------------------------------
|
| Todas as rotas API ficam atrás do prefix `api/v1` (ver
| `bootstrap/app.php`). O middleware `ResolveTenant` (T042) é aplicado
| globalmente ao grupo: o tenant é resolvido pelo subdomínio do request
| antes de qualquer Controller rodar.
|
| Slugs reservados (`crm`, `admin`, `www`, etc.) bypassam o middleware
| — rotas públicas (cadastro, super admin) ficam aqui também.
|
*/

// Rota dummy de smoke-test para o middleware `ResolveTenant`. Será
// substituída por endpoints reais (login, me, register) nos próximos
// lotes. Mantida para o gate `TenantIsolationTest`.
Route::get('/_ping', function () {
    return app()->bound('tenant')
        ? response(app('tenant')->slug, 200)
        : response()->json(['tenant' => null], 200);
});

// T050 — Rota dummy autenticada para Sanctum::actingAs (SanctumStatefulTest).
// Retorna id, email e tenant_id do usuário autenticado.
Route::middleware('auth:sanctum')->get('/_me', fn (Request $request) => response()->json(
    $request->user()->only(['id', 'email', 'tenant_id'])
));

// Lote D — Fase 4: Bearer token auth endpoints.
// POST /auth/login emite Personal Access Token (substitui cookie/session).
// Rotas autenticadas: auth:sanctum + tenant.slug (X-Tenant-Slug header check) + slide.token.
Route::prefix('auth')->group(function (): void {
    Route::post('login', LoginController::class)
        ->middleware(['throttle:login'])
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'tenant.slug', 'slide.token'])->group(function (): void {
        Route::post('logout', LogoutController::class)->name('auth.logout');
        Route::post('logout-all', LogoutAllController::class)->name('auth.logout_all');
        Route::get('me', MeController::class)->name('auth.me');
        Route::get('tokens', [TokensController::class, 'index'])->name('auth.tokens.index');
        Route::delete('tokens/{tokenId}', [TokensController::class, 'destroy'])
            ->whereNumber('tokenId')
            ->name('auth.tokens.destroy');
    });
});

// US-1.1 — Cadastro público de tenant (T143). Servido apenas em hosts
// públicos (`crm.lvh.me` em dev, `crm.com.br` em prod) — o middleware
// `ResolveTenant` ignora `public_hosts` e segue direto.
Route::post('/tenants/register', TenantRegisterController::class)
    ->middleware('throttle:tenant-register')
    ->name('tenants.register');

// US-1.1 — Leitura do tenant atual (T144). Resolvido pelo subdomínio via
// `ResolveTenant`. Sem auth (usado pelo SPA na inicialização).
Route::get('/tenant', CurrentTenantController::class)->name('tenant.current');

// US-2.3 — Recuperação de senha (T122).
Route::middleware('throttle:password-forgot')->group(function (): void {
    Route::post('/auth/password/forgot', [PasswordController::class, 'forgot'])
        ->name('auth.password.forgot');
});

Route::middleware('throttle:api')->group(function (): void {
    Route::post('/auth/password/reset', [PasswordController::class, 'reset'])
        ->name('auth.password.reset');
});

// US-1.2 — Wizard de onboarding do tenant (T162). Exige auth Sanctum e tenant
// não suspenso. Autorização por role (`admin-clinica`) feita via OnboardingPolicy.
Route::middleware(['auth:sanctum', 'tenant.not-suspended'])->group(function (): void {
    Route::get('/onboarding/state', [OnboardingController::class, 'state'])
        ->name('onboarding.state');
    Route::post('/onboarding/steps/{stepKey}/complete', [OnboardingController::class, 'complete'])
        ->name('onboarding.complete');
    Route::post('/onboarding/steps/{stepKey}/skip', [OnboardingController::class, 'skip'])
        ->name('onboarding.skip');
});

// US-1.5 (Spec 010) — Dashboard Home consolidado.
Route::middleware(['auth:sanctum', 'tenant.not-suspended'])->group(function (): void {
    Route::get('/panel/home', PanelHomeController::class)
        ->name('panel.home');
});

// US-1.2.2 (Spec 012) — Gestão de Profissionais (CRUD + activate + autocomplete + check-email).
Route::middleware(['auth:sanctum', 'tenant.slug', 'tenant.not-suspended'])
    ->prefix('professionals')
    ->name('professionals.')
    ->group(function (): void {
        // Rotas estáticas ANTES da rota com {professional} para evitar colisão.
        Route::get('/especialidades', EspecialidadesAutocompleteController::class)
            ->name('especialidades');
        Route::post('/check-email', CheckEmailController::class)
            ->name('check-email');
        Route::post('/{professional}/activate', ProfessionalActivateController::class)
            ->whereNumber('professional')
            ->name('activate');
    });

Route::middleware(['auth:sanctum', 'tenant.slug', 'tenant.not-suspended'])->group(function (): void {
    Route::apiResource('/professionals', ProfessionalsController::class)
        ->parameters(['professionals' => 'professional']);
});

// Feature 013 — Catálogo de templates de notificação outbound (US5).
Route::middleware(['auth:sanctum', 'tenant.slug', 'tenant.not-suspended'])->group(function (): void {
    Route::apiResource('/notification-templates', NotificationTemplateController::class)
        ->parameters(['notification-templates' => 'notificationTemplate'])
        ->only(['index', 'store', 'update', 'destroy']);
});

// US-1.3 — Billing: planos públicos (sem auth) e checkout/assinatura autenticados.
Route::get('/billing/plans', PlansController::class)->name('billing.plans');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/billing/checkout', CheckoutController::class)
        ->middleware('tenant.not-suspended')
        ->name('billing.checkout');
    Route::get('/billing/subscription', [SubscriptionController::class, 'show'])
        ->name('billing.subscription.show');
    Route::patch('/billing/subscription', [SubscriptionController::class, 'patch'])
        ->middleware('tenant.not-suspended')
        ->name('billing.subscription.patch');

    Route::get('/billing/ai-usage', [AiUsageController::class, 'show'])
        ->name('billing.ai-usage.show');

    Route::patch('/billing/ai-usage/hard-cap', [AiUsageController::class, 'patchHardCap'])
        ->middleware('tenant.not-suspended')
        ->name('billing.ai-usage.hard-cap');
});

// US-2.2 — Gestão de usuários internos (T244).
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/users', [UsersController::class, 'index'])->name('users.index');
    Route::patch('/users/{id}', [UsersController::class, 'update'])->name('users.update');
    Route::delete('/users/{id}', [UsersController::class, 'destroy'])->name('users.destroy');
    Route::get('/users/invitations', [InvitationsController::class, 'index'])->name('users.invitations.index');
    Route::post('/users/invitations', [InvitationsController::class, 'store'])->name('users.invitations.store');
    Route::delete('/users/invitations/{id}', [InvitationsController::class, 'destroy'])->name('users.invitations.destroy');
});

// US-2.2 — Aceite de convite: endpoint público (sem auth), resolvido por subdomínio.
Route::post('/users/invitations/accept', [InvitationsController::class, 'accept'])
    ->middleware('throttle:api')
    ->name('users.invitations.accept');

// US-2.4 — Painel de auditoria e export CSV (T262 + T263).
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/audit-logs/export', [AuditLogsController::class, 'export'])->name('audit-logs.export');
    Route::get('/audit-logs', [AuditLogsController::class, 'index'])->name('audit-logs.index');
});

// US-3.3 — Exportação CSV de pacientes (T194). Throttle `export`.
Route::middleware(['auth:sanctum', 'tenant.not-suspended'])->group(function (): void {
    Route::get('/pacientes/exportar', ExportacaoController::class)
        ->middleware('throttle:export')
        ->name('pacientes.exportar');
});

// US-3.3 — Importação de pacientes (T188).
Route::middleware(['auth:sanctum', 'tenant.not-suspended'])->group(function (): void {
    Route::get('/pacientes/importacao/template', [ImportacaoController::class, 'template'])
        ->name('pacientes.import.template');
    Route::post('/pacientes/importacao', [ImportacaoController::class, 'store'])
        ->middleware('throttle:import')
        ->name('pacientes.import.store');
    Route::get('/pacientes/importacao/{id}', [ImportacaoController::class, 'show'])
        ->name('pacientes.import.show')
        ->whereNumber('id');
});

// US-3.4 — Funil Kanban (T215)
Route::middleware(['auth:sanctum', 'tenant.not-suspended'])->group(function (): void {
    Route::get('/funil/colunas', [FunilController::class, 'colunas'])
        ->name('funil.colunas');
    Route::patch('/funil/colunas/{id}', [FunilController::class, 'updateColuna'])
        ->name('funil.colunas.update')
        ->whereNumber('id');
});

// Fase 2 — US-3.1 (T120): endpoints de pacientes.
// Substitui o stub de T030.
Route::middleware(['auth:sanctum', 'tenant.not-suspended'])->prefix('pacientes')->group(function (): void {
    // Mesclagem (antes dos {id} para não conflitar com whereNumber)
    Route::post('/mesclagens', [MesclagemController::class, 'store'])
        ->name('pacientes.mesclagens.store');

    Route::post('/mesclagens/{id}/reverter', [MesclagemController::class, 'reverter'])
        ->name('pacientes.mesclagens.reverter')
        ->whereNumber('id');

    // CRUD pacientes
    Route::get('/', [PacientesController::class, 'index'])->name('pacientes.index');
    Route::post('/', [PacientesController::class, 'store'])->name('pacientes.store');
    Route::get('/{id}', [PacientesController::class, 'show'])->name('pacientes.show')->whereNumber('id');
    Route::patch('/{id}', [PacientesController::class, 'update'])->name('pacientes.update')->whereNumber('id');
    Route::delete('/{id}', [PacientesController::class, 'destroy'])->name('pacientes.destroy')->whereNumber('id');
    Route::patch('/{id}/status', PatchStatusController::class)->name('pacientes.status')->whereNumber('id');
    Route::post('/{id}/anonimizar', [PacientesController::class, 'anonimizar'])->name('pacientes.anonimizar')->whereNumber('id');

    // US2 — Timeline (T155)
    Route::get('/{id}/timeline', TimelineController::class)->name('pacientes.timeline')->whereNumber('id');

    // US2 — Anotações (T155)
    Route::get('/{id}/anotacoes', [AnotacoesController::class, 'index'])->name('pacientes.anotacoes.index')->whereNumber('id');
    Route::post('/{id}/anotacoes', [AnotacoesController::class, 'store'])->name('pacientes.anotacoes.store')->whereNumber('id');
    Route::post('/{id}/anotacoes/{anotacao_id}/retratacao', [AnotacoesController::class, 'retratar'])
        ->name('pacientes.anotacoes.retratar')
        ->whereNumber('id')
        ->whereNumber('anotacao_id');

    // US4 — Funil Kanban: mover card (T215)
    Route::patch('/{id}/funil', [FunilController::class, 'moveCard'])
        ->name('pacientes.funil.move')
        ->whereNumber('id');

    // US5 — Tags: aplicar e remover (T246)
    Route::post('/{id}/tags', [PacienteTagsController::class, 'attach'])
        ->name('pacientes.tags.attach')
        ->whereNumber('id');

    Route::delete('/{id}/tags/{tag_id}', [PacienteTagsController::class, 'detach'])
        ->name('pacientes.tags.detach')
        ->whereNumber('id')
        ->whereNumber('tag_id');
});

// US5 — Tags: catálogo (T246)
Route::middleware(['auth:sanctum', 'tenant.not-suspended'])->group(function (): void {
    Route::get('/tags', [TagsController::class, 'index'])->name('tags.index');
    Route::post('/tags', [TagsController::class, 'store'])->name('tags.store');
});

// US5 — Convênios: catálogo CRUD (T246)
Route::middleware(['auth:sanctum', 'tenant.not-suspended'])->group(function (): void {
    Route::get('/convenios', [ConveniosController::class, 'index'])->name('convenios.index');
    Route::post('/convenios', [ConveniosController::class, 'store'])->name('convenios.store');
    Route::patch('/convenios/{id}', [ConveniosController::class, 'update'])->name('convenios.update')->whereNumber('id');
    Route::delete('/convenios/{id}', [ConveniosController::class, 'destroy'])->name('convenios.destroy')->whereNumber('id');
});

// Fase 3 — US1: Canais de mensageria (T077)
// Exige auth Sanctum + throttle inbox. `auth:sanctum` já garante tenant via
// ResolveTenant (roda global no grupo 'api').
Route::middleware(['auth:sanctum', 'throttle:inbox'])->group(function (): void {
    Route::apiResource('inbox/channels', ChannelsController::class);
    Route::post('inbox/channels/{channel}/reconnect', [ChannelsController::class, 'reconnect'])
        ->name('inbox.channels.reconnect')
        ->whereNumber('channel');
    Route::get('inbox/channels/{channel}/templates', [ChannelTemplatesController::class, 'index'])
        ->name('inbox.channels.templates.index')
        ->whereNumber('channel');
    Route::post('inbox/channels/{channel}/templates/sync', [ChannelTemplatesController::class, 'sync'])
        ->name('inbox.channels.templates.sync')
        ->whereNumber('channel');

    // Fase 3 — US3: Widget Web (T213) — Admin endpoints
    Route::prefix('inbox')->group(function (): void {
        Route::get('widget-configs/{channelId}', [WidgetConfigController::class, 'show'])
            ->name('inbox.widget-configs.show')
            ->whereNumber('channelId');
        Route::put('widget-configs/{channelId}', [WidgetConfigController::class, 'update'])
            ->name('inbox.widget-configs.update')
            ->whereNumber('channelId');
        Route::get('widget-configs/{channelId}/snippet', [WidgetConfigController::class, 'snippet'])
            ->name('inbox.widget-configs.snippet')
            ->whereNumber('channelId');
    });
});

// Fase 3 — US4: Inbox Conversations + Messages (T114/T115/T120/T121)
Route::middleware(['auth:sanctum', 'throttle:inbox'])->prefix('inbox')->group(function (): void {
    Route::get('conversations', [ConversationsController::class, 'index'])
        ->name('inbox.conversations.index');
    Route::get('conversations/{conversation}', [ConversationsController::class, 'show'])
        ->name('inbox.conversations.show')
        ->whereNumber('conversation');
    Route::get('conversations/{conversation}/messages', [MessagesController::class, 'index'])
        ->name('inbox.conversations.messages.index')
        ->whereNumber('conversation');
    Route::post('conversations/{conversation}/messages', [MessagesController::class, 'store'])
        ->name('inbox.conversations.messages.store')
        ->whereNumber('conversation');
    Route::post('conversations/{conversation}/read', [ConversationsController::class, 'read'])
        ->name('inbox.conversations.read')
        ->whereNumber('conversation');
    Route::post('conversations/{conversation}/resolve', [ConversationsController::class, 'resolve'])
        ->name('inbox.conversations.resolve')
        ->whereNumber('conversation');
    Route::post('conversations/{conversation}/reopen', [ConversationsController::class, 'reopen'])
        ->name('inbox.conversations.reopen')
        ->whereNumber('conversation');
    Route::post('conversations/{conversation}/link-patient', [ConversationsController::class, 'linkPatient'])
        ->name('inbox.conversations.link-patient')
        ->whereNumber('conversation');
    Route::get('poll', InboxPollController::class)
        ->name('inbox.poll');

    // US-4.6 — Modo Humano Assume (T178)
    Route::post('conversations/{conversation}/takeover', [TakeoverController::class, 'takeover'])
        ->name('inbox.conversations.takeover')
        ->whereNumber('conversation');
    Route::post('conversations/{conversation}/release-to-ai', [TakeoverController::class, 'releaseToAi'])
        ->name('inbox.conversations.release-to-ai')
        ->whereNumber('conversation');

    // US-4.5 — Atribuição e transferência (T155)
    Route::post('conversations/{conversation}/assign', [AssignmentsController::class, 'assign'])
        ->name('inbox.conversations.assign')
        ->whereNumber('conversation');
    Route::post('conversations/{conversation}/transfer', [AssignmentsController::class, 'transfer'])
        ->name('inbox.conversations.transfer')
        ->whereNumber('conversation');
    Route::get('conversations/{conversation}/assignments', [AssignmentsController::class, 'assignments'])
        ->name('inbox.conversations.assignments')
        ->whereNumber('conversation');
    Route::get('assignment-rules', [AssignmentRulesController::class, 'index'])
        ->name('inbox.assignment-rules.index');
    Route::put('assignment-rules', [AssignmentRulesController::class, 'update'])
        ->name('inbox.assignment-rules.update');

    // US-4.7 — Respostas Rápidas (T240)
    Route::apiResource('quick-replies', QuickRepliesController::class)
        ->except(['show'])
        ->names([
            'index' => 'inbox.quick-replies.index',
            'store' => 'inbox.quick-replies.store',
            'update' => 'inbox.quick-replies.update',
            'destroy' => 'inbox.quick-replies.destroy',
        ]);
    Route::post('quick-replies/{quickReply}/render', [QuickRepliesController::class, 'render'])
        ->name('inbox.quick-replies.render')
        ->whereNumber('quickReply');

    // T252 — Media upload/download pré-assinado (NC-9)
    Route::post('media/upload', [MediaController::class, 'upload'])
        ->name('inbox.media.upload');
    Route::get('media/{id}', [MediaController::class, 'show'])
        ->name('inbox.media.show')
        ->whereNumber('id');

    // T264 — Presença de atendentes (NC-6.b)
    Route::post('presence/heartbeat', [PresenceController::class, 'heartbeat'])
        ->name('inbox.presence.heartbeat');
    Route::get('presence', [PresenceController::class, 'index'])
        ->name('inbox.presence.index');
    Route::patch('presence/me', [PresenceController::class, 'updateMe'])
        ->name('inbox.presence.me');
});

// Fase 3 — Webhooks Twilio (T081)
// Fora do grupo auth:sanctum — webhooks são públicos mas validados por assinatura HMAC.
// `ResolveTenant` global ainda executa mas não há tenant resolvido aqui (sem subdomínio tenant).
Route::middleware(['throttle:webhook-meta'])->group(function (): void {
    Route::post('webhooks/twilio/whatsapp', TwilioWhatsAppWebhookController::class)
        ->middleware('twilio.signature')
        ->name('webhooks.twilio.whatsapp');
    Route::post('webhooks/twilio/status', TwilioStatusCallbackController::class)
        ->middleware('twilio.signature')
        ->name('webhooks.twilio.status');
});

// Fase 3 — Webhooks Meta Instagram (T194)
// GET: handshake sem assinatura (verificação do URL pelo Meta).
// POST: inbound DMs — assinatura HMAC SHA256 validada pelo middleware.
// Ambos fora do grupo auth:sanctum — webhook é público, sem tenant context.
// Canal resolvido no job via ig_business_account_id do payload.
Route::middleware(['throttle:webhook-meta'])->group(function (): void {
    Route::get('webhooks/instagram', [MetaInstagramWebhookController::class, 'verify'])
        ->name('webhooks.instagram.verify');
    Route::post('webhooks/instagram', MetaInstagramWebhookController::class)
        ->middleware(ValidateMetaSignature::class)
        ->name('webhooks.instagram.inbound');
});

/*
|--------------------------------------------------------------------------
| T026 (Fase 5) — Agenda de Consultas (US-6.1..6.7)
|--------------------------------------------------------------------------
|
| Rotas autenticadas: Bearer + X-Tenant-Slug (Fase 4 triple-check) +
| feature flag opcional `agenda.enabled` (T028 EnsureAgendaModuleEnabled).
|
| Filled per US:
|   T046 (US1 schedules), T057 (US2 types), T084 (US3 appointments+slots),
|   T113 (US4 confirm+attendance), T124 (US5 cancel),
|   T137 (US6 waitlist), T168 (US7 sync).
|
| Webhook Google Calendar fica fora deste grupo — validado por HMAC do header
| X-Goog-Channel-Token em routes/web.php (T164).
*/
Route::middleware(['auth:sanctum', 'tenant.slug', 'tenant.not-suspended', 'agenda.enabled'])
    ->prefix('agenda')
    ->name('agenda.')
    ->group(function (): void {
        // T046 (US-6.1) — Agenda recorrente + bloqueios pontuais
        Route::get('professionals/{professional}/schedules', [ProfessionalScheduleController::class, 'show'])
            ->name('schedules.show');
        Route::put('professionals/{professional}/schedules', [ProfessionalScheduleController::class, 'update'])
            ->name('schedules.update');
        Route::get('professionals/{professional}/schedule-exceptions', [ScheduleExceptionController::class, 'index'])
            ->name('schedule-exceptions.index');
        Route::post('professionals/{professional}/schedule-exceptions', [ScheduleExceptionController::class, 'store'])
            ->name('schedule-exceptions.store');
        Route::delete('schedule-exceptions/{schedule_exception}', [ScheduleExceptionController::class, 'destroy'])
            ->name('schedule-exceptions.destroy');

        // T084 (US-6.3) — Appointments + slots (search/reserve/release)
        Route::get('consultas', [AppointmentController::class, 'index'])
            ->name('consultas.index');
        Route::post('consultas', [AppointmentController::class, 'store'])
            ->middleware('throttle:120,1')
            ->name('consultas.store');
        Route::get('consultas/{appointment}', [AppointmentController::class, 'show'])
            ->name('consultas.show');
        Route::post('consultas/{appointment}/reagendar', [AppointmentController::class, 'reschedule'])
            ->name('consultas.reschedule');
        // T113 (US-6.4) — Confirmação automática + comparecimento
        Route::post('consultas/{appointment}/confirmar-resposta', [AppointmentController::class, 'confirmResponse'])
            ->name('consultas.confirm-response');
        Route::post('consultas/{appointment}/marcar-comparecimento', [AppointmentController::class, 'markAttendance'])
            ->name('consultas.mark-attendance');
        Route::post('consultas/{appointment}/reverter-comparecimento', [AppointmentController::class, 'revertAttendance'])
            ->name('consultas.revert-attendance');
        // T124 (US-6.5) — Cancelamento via chat com política de prazo
        Route::post('consultas/{appointment}/cancelar', [AppointmentController::class, 'cancel'])
            ->name('consultas.cancel');

        // T137 (US-6.6) — Lista de espera FIFO sequencial K=1
        Route::get('waitlist', [WaitlistController::class, 'index'])
            ->name('waitlist.index');
        Route::post('waitlist', [WaitlistController::class, 'store'])
            ->name('waitlist.store');
        Route::delete('waitlist/{waitlist}', [WaitlistController::class, 'destroy'])
            ->name('waitlist.destroy');
        Route::post('waitlist/{waitlist}/aceitar', [WaitlistController::class, 'accept'])
            ->name('waitlist.accept');

        Route::get('slots-disponiveis', [SlotController::class, 'listAvailable'])
            ->name('slots.available');
        Route::post('slots/{starts_at}/reservar', [SlotController::class, 'reservar'])
            ->middleware('throttle:60,1')
            ->name('slots.reserve');
        Route::delete('slot-reservations/{slot_reservation}', [SlotController::class, 'releaseReservation'])
            ->name('slots.reservation.release');

        // T057 (US-6.2) — Tipos de atendimento
        Route::get('appointment-types', [AppointmentTypeController::class, 'index'])
            ->name('appointment-types.index');
        Route::post('appointment-types', [AppointmentTypeController::class, 'store'])
            ->name('appointment-types.store');
        Route::get('appointment-types/{appointment_type}', [AppointmentTypeController::class, 'show'])
            ->name('appointment-types.show');
        Route::patch('appointment-types/{appointment_type}', [AppointmentTypeController::class, 'update'])
            ->name('appointment-types.update');
        Route::delete('appointment-types/{appointment_type}', [AppointmentTypeController::class, 'destroy'])
            ->name('appointment-types.destroy');
    });

/*
|--------------------------------------------------------------------------
| Prescriptions (Fase 7 — Épico 8)
|
| T070 — US-8.1 Cadastro de Receituário.
| Middleware `prescription.module` gate de plano via tenant.settings.
|
| Routes:
|   GET    /api/v1/prescriptions                    → index
|   POST   /api/v1/prescriptions                    → store  (throttle:120,1)
|   GET    /api/v1/prescriptions/{prescription}     → show
|   PATCH  /api/v1/prescriptions/{prescription}     → update (notes only)
|   POST   /api/v1/prescriptions/{prescription}/cancel → cancel
|   POST   /api/v1/prescriptions/{prescription}/pdf → upload
|   GET    /api/v1/prescriptions/{prescription}/pdf → download
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant.slug', 'tenant.not-suspended', 'prescription.module'])
    ->prefix('prescriptions')
    ->name('prescriptions.')
    ->group(function (): void {
        Route::get('/', [PrescriptionController::class, 'index'])
            ->name('index');

        Route::post('/', [PrescriptionController::class, 'store'])
            ->middleware('throttle:120,1')
            ->name('store');

        Route::get('/{prescription}', [PrescriptionController::class, 'show'])
            ->name('show');

        Route::patch('/{prescription}', [PrescriptionController::class, 'update'])
            ->name('update');

        Route::post('/{prescription}/cancel', [PrescriptionController::class, 'cancel'])
            ->name('cancel');

        Route::post('/{prescription}/pdf', [PrescriptionPdfController::class, 'upload'])
            ->name('pdf.upload');

        Route::get('/{prescription}/pdf', [PrescriptionPdfController::class, 'download'])
            ->name('pdf.download');

        // T112 — US-8.2 Alertas de vencimento
        Route::get('/{prescription}/alerts', [PrescriptionAlertConfigController::class, 'index'])
            ->name('alerts.index');

        Route::patch('/{prescription}/alert-config', [PrescriptionAlertConfigController::class, 'update'])
            ->name('alert-config.update');

        // T140 — US-8.3 Renovação via IA
        Route::post('/{prescription}/renew', [PrescriptionRenewalController::class, 'store'])
            ->name('renew');
    });

/*
|--------------------------------------------------------------------------
| AI Prescriptions (Fase 7 — US-8.3 Renovação via IA)
|
| T140 — Endpoint de contexto pseudonimizado para a IA.
| Requer ability `prescription.ai_context` no token Sanctum.
|
| Routes:
|   GET /api/v1/ai/prescriptions/{prescription}/context → context
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant.slug', 'tenant.not-suspended', 'prescription.module'])
    ->prefix('ai/prescriptions')
    ->name('ai.prescriptions.')
    ->group(function (): void {
        Route::get('/{prescription}/context', [PrescriptionContextForAiController::class, 'show'])
            ->name('context');
    });

/*
|--------------------------------------------------------------------------
| Prescription Reports (Fase 7 — US-8.4 Relatório)
|
| T162 — Relatório de receitas com cursor pagination + exportação CSV.
|
| Routes:
|   GET /api/v1/prescription-reports         → index (cursor paginated)
|   GET /api/v1/prescription-reports/export  → export CSV (streamed)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant.slug', 'tenant.not-suspended', 'prescription.module'])
    ->prefix('prescription-reports')
    ->name('prescription-reports.')
    ->group(function (): void {
        Route::get('/', [PrescriptionReportController::class, 'index'])
            ->name('index');

        Route::get('/export', [PrescriptionCsvExportController::class, 'export'])
            ->name('export');
    });

/*
|--------------------------------------------------------------------------
| Privacy / LGPD (Fase 8 — Lote A US-13.1)
|
| T031 — Endpoints de consentimento granular hierárquico (Q24).
|
| Routes:
|   GET  /api/v1/privacy/consents              → listagem paginada filtros
|   GET  /api/v1/privacy/consents/{consent}    → ficha individual
|   POST /api/v1/privacy/consents              → registrar granted ou refused
|   POST /api/v1/privacy/consents/revoke       → revogar por (patient,finalidade)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant.slug', 'tenant.not-suspended'])
    ->prefix('privacy')
    ->name('privacy.')
    ->group(function (): void {
        Route::get('/consents', [ConsentsController::class, 'index'])
            ->name('consents.index');

        Route::post('/consents', [ConsentsController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('consents.store');

        Route::post('/consents/revoke', [ConsentsController::class, 'revoke'])
            ->middleware('throttle:60,1')
            ->name('consents.revoke');

        Route::get('/consents/{consent}', [ConsentsController::class, 'show'])
            ->name('consents.show');

        /*
        | T055 — Direito ao Esquecimento (US-13.2 — LGPD Art. 18º)
        */
        Route::get('/forgetting-requests', [ForgettingController::class, 'index'])
            ->name('forgetting.index');

        Route::post('/forgetting-requests', [ForgettingController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('forgetting.store');

        Route::get('/forgetting-requests/{forgetting}', [ForgettingController::class, 'show'])
            ->name('forgetting.show');

        Route::post('/forgetting-requests/{forgetting}/execute', [ForgettingController::class, 'execute'])
            ->middleware('throttle:10,1')
            ->name('forgetting.execute');

        Route::post('/forgetting-requests/{forgetting}/deny', [ForgettingController::class, 'deny'])
            ->middleware('throttle:10,1')
            ->name('forgetting.deny');

        /*
        | T055 — Portabilidade de Dados (US-13.2 — LGPD Art. 18º V — Q28)
        */
        Route::get('/portability-requests', [PortabilityController::class, 'index'])
            ->name('portability.index');

        Route::post('/portability-requests', [PortabilityController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('portability.store');

        Route::get('/portability-requests/{portability}', [PortabilityController::class, 'show'])
            ->name('portability.show');

        Route::post('/portability-requests/{portability}/execute', [PortabilityController::class, 'execute'])
            ->middleware('throttle:10,1')
            ->name('portability.execute');
    });

/*
|--------------------------------------------------------------------------
| Campaigns (Fase 8 — Lote C US-9.1)
|
| T167 — Endpoints REST de campanhas. Pipeline FormRequest → Service → Resource.
|
| Routes:
|   GET    /api/v1/campaigns                          → index
|   POST   /api/v1/campaigns                          → store (throttle:30,1)
|   GET    /api/v1/campaigns/{campaign}               → show
|   PATCH  /api/v1/campaigns/{campaign}               → update (draft/scheduled only)
|   POST   /api/v1/campaigns/{campaign}/preview       → audience + warnings
|   POST   /api/v1/campaigns/{campaign}/dispatch      → dispatch (throttle:5,1)
|   POST   /api/v1/campaigns/{campaign}/cancel        → cancel
|   GET    /api/v1/campaigns/{campaign}/report        → relatório agregado
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant.slug', 'tenant.not-suspended'])
    ->prefix('campaigns')
    ->name('campaigns.')
    ->group(function (): void {
        Route::get('/', [CampaignsController::class, 'index'])->name('index');

        Route::post('/', [CampaignsController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('store');

        Route::get('/{campaign}', [CampaignsController::class, 'show'])->name('show');

        Route::patch('/{campaign}', [CampaignsController::class, 'update'])->name('update');

        Route::post('/{campaign}/preview', [CampaignsController::class, 'preview'])
            ->middleware('throttle:60,1')
            ->name('preview');

        Route::post('/{campaign}/dispatch', [CampaignsController::class, 'dispatchCampaign'])
            ->middleware('throttle:5,1')
            ->name('dispatch');

        Route::post('/{campaign}/cancel', [CampaignsController::class, 'cancel'])
            ->middleware('throttle:30,1')
            ->name('cancel');

        Route::get('/{campaign}/report', [CampaignsController::class, 'report'])->name('report');
    });

/*
|--------------------------------------------------------------------------
| Reports (Fase 8 — Lote E US-10.1 / US-10.2 / US-10.3)
|
| T257/T269/T275 — Endpoints REST de dashboard executivo, operacional e clínico.
| Policy `ReportPolicy` enforça abilities `report.view` / `report.export`.
| Escopo por perfil (Q13) aplicado em ClinicalReportService.
|
| Routes:
|   GET  /api/v1/reports/executive                  → dashboard snapshot
|   GET  /api/v1/reports/executive/drill/{metric}   → drill-down lista
|   POST /api/v1/reports/executive/export-pdf       → PDF formatado
|   GET  /api/v1/reports/operational                → relatório operacional
|   GET  /api/v1/reports/clinical                   → relatório clínico
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant.slug', 'tenant.not-suspended'])
    ->prefix('reports')
    ->name('reports.')
    ->group(function (): void {
        Route::get('/executive', [ExecutiveDashboardController::class, 'show'])
            ->middleware('throttle:60,1')
            ->name('executive.show');

        Route::get('/executive/drill/{metric}', [ExecutiveDashboardController::class, 'drillDown'])
            ->middleware('throttle:60,1')
            ->name('executive.drill');

        Route::post('/executive/export-pdf', [ExecutiveDashboardController::class, 'exportPdf'])
            ->middleware('throttle:10,1')
            ->name('executive.export-pdf');

        Route::get('/operational', [OperationalReportController::class, 'show'])
            ->middleware('throttle:60,1')
            ->name('operational.show');

        Route::get('/clinical', [ClinicalReportController::class, 'show'])
            ->middleware('throttle:60,1')
            ->name('clinical.show');
    });

/*
|--------------------------------------------------------------------------
| Integrations — Webhooks (Fase 8 — Lote D US-11.1)
|--------------------------------------------------------------------------
|
| T200 — CRUD + DLQ. WebhookPolicy autoriza via abilities `webhook.manage`,
| `webhook.view_deliveries`, `webhook.resend_dlq`.
|
| Routes:
|   GET    /integrations/webhooks                       → index
|   POST   /integrations/webhooks                       → store (secret 1x)
|   GET    /integrations/webhooks/dlq                   → DLQ list
|   POST   /integrations/webhooks/dlq/{dlq}/resend     → reenviar
|   GET    /integrations/webhooks/{webhook}             → show
|   PATCH  /integrations/webhooks/{webhook}             → update
|   DELETE /integrations/webhooks/{webhook}             → destroy
|   POST   /integrations/webhooks/{webhook}/pause       → pause/resume
|   GET    /integrations/webhooks/{webhook}/deliveries  → histórico
*/
Route::middleware(['auth:sanctum', 'tenant.slug', 'tenant.not-suspended'])
    ->prefix('integrations/webhooks')
    ->name('integrations.webhooks.')
    ->group(function (): void {
        // DLQ tem que vir ANTES do {webhook} para não conflitar com binding.
        Route::get('/dlq', [WebhooksController::class, 'listDeadLetter'])->name('dlq.index');
        Route::post('/dlq/{dlq}/resend', [WebhooksController::class, 'resendFromDlq'])
            ->whereNumber('dlq')
            ->middleware('throttle:30,1')
            ->name('dlq.resend');

        Route::get('/', [WebhooksController::class, 'index'])->name('index');
        Route::post('/', [WebhooksController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('store');
        Route::get('/{webhook}', [WebhooksController::class, 'show'])
            ->whereNumber('webhook')
            ->name('show');
        Route::patch('/{webhook}', [WebhooksController::class, 'update'])
            ->whereNumber('webhook')
            ->name('update');
        Route::delete('/{webhook}', [WebhooksController::class, 'destroy'])
            ->whereNumber('webhook')
            ->name('destroy');
        Route::post('/{webhook}/pause', [WebhooksController::class, 'pauseResume'])
            ->whereNumber('webhook')
            ->name('pause');
        Route::get('/{webhook}/deliveries', [WebhooksController::class, 'listDeliveries'])
            ->whereNumber('webhook')
            ->name('deliveries');
    });

/*
|--------------------------------------------------------------------------
| Integrations — API Tokens (Fase 8 — Lote D US-11.2)
|--------------------------------------------------------------------------
|
| T231 — CRUD interno de Personal Access Tokens para a API pública.
| Apenas admin-clinica (ability `api_token.manage`).
*/
Route::middleware(['auth:sanctum', 'tenant.slug', 'tenant.not-suspended'])
    ->prefix('integrations/api-tokens')
    ->name('integrations.api_tokens.')
    ->group(function (): void {
        Route::get('/', [ApiTokensController::class, 'index'])->name('index');
        Route::post('/', [ApiTokensController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('store');
        Route::delete('/{tokenId}', [ApiTokensController::class, 'destroy'])
            ->whereNumber('tokenId')
            ->name('destroy');
    });
