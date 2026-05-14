<?php

namespace App\Providers;

use App\Domain\Auth\Contracts\BearerAuthContract;
use App\Domain\Auth\Services\SuspiciousTokenUsageDetector;
use App\Domain\Auth\Services\TokenIssuerService;
use App\Domain\Messaging\Assignment\Models\AssignmentRule;
use App\Domain\Messaging\Channel\Adapters\WhatsAppCloudAdapter;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Contracts\ConversaIATogglingContract;
use App\Domain\Messaging\Conversation\Events\ConversaCriada;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Conversation\Services\HumanTakeoverService;
use App\Domain\Messaging\Infrastructure\CircuitBreaker\CircuitBreakerService;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Observers\MessageObserver;
use App\Domain\Messaging\Message\Services\MessageDispatchService;
use App\Domain\Messaging\QuickReply\Models\QuickReply;
use App\Events\Agenda\ConsultaConfirmacaoPendente;
use App\Events\Agenda\ConsultaCriada;
use App\Events\TenantResolved;
use App\Listeners\Agenda\DispatchConfirmationToInbox;
use App\Listeners\Agenda\MoveCardToAgendadoColumn;
use App\Models\Agenda\Appointment;
use App\Models\Agenda\AppointmentType;
use App\Models\Anotacao;
use App\Models\AuditLog;
use App\Models\Convenio;
use App\Models\FunilColuna;
use App\Models\Invitation;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\Agenda\AppointmentPolicy;
use App\Policies\Agenda\AppointmentTypePolicy;
use App\Policies\Agenda\ProfessionalSchedulePolicy;
use App\Policies\AnotacaoPolicy;
use App\Policies\AssignmentPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\ChannelPolicy;
use App\Policies\ConvenioPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\FunilPolicy;
use App\Policies\InvitationPolicy;
use App\Policies\MessagePolicy;
use App\Policies\OnboardingPolicy;
use App\Policies\PacientePolicy;
use App\Policies\QuickReplyPolicy;
use App\Policies\TagPolicy;
use App\Policies\UserPolicy;
use App\Services\Billing\StripeClientWrapper;
use App\Support\Metrics\AgendaMetrics;
use App\Support\Metrics\AgendaMetricsContract;
use App\Support\Metrics\AuthMetrics;
use App\Support\Metrics\AuthMetricsContract;
use App\Support\Metrics\MessagingMetrics;
use App\Support\Metrics\MessagingMetricsContract;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Sanctum\Events\TokenAuthenticated;
use Laravel\Sanctum\PersonalAccessToken;
use Sentry\Breadcrumb;
use Sentry\State\Scope;
use Spatie\Permission\PermissionRegistrar;
use Stripe\StripeClient;
use Twilio\Rest\Client;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra serviços no container.
     */
    public function register(): void
    {
        // Fase 3 — WhatsAppCloudAdapter: singleton com CircuitBreakerService.
        // O Twilio Client é instanciado por channel dentro do adapter (send/validateCredentials).
        // `$clientOverride` fica null em produção; testes injetam via app->instance(Client::class, $mock).
        $this->app->singleton(WhatsAppCloudAdapter::class, function () {
            $clientOverride = $this->app->bound(Client::class)
                ? $this->app->make(Client::class)
                : null;

            return new WhatsAppCloudAdapter(
                $this->app->make(CircuitBreakerService::class),
                $clientOverride,
            );
        });

        // Fase 3 — MessageDispatchService: singleton com WhatsAppCloudAdapter.
        $this->app->singleton(MessageDispatchService::class, function () {
            return new MessageDispatchService(
                $this->app->make(WhatsAppCloudAdapter::class),
            );
        });

        // Fase 3 US-4.6 — HumanTakeoverService: singleton que implementa ConversaIATogglingContract.
        // Contrato congelado para Fase 4 (Princípio III).
        $this->app->singleton(ConversaIATogglingContract::class, HumanTakeoverService::class);

        // T269 — MessagingMetrics: singleton com degradação graceful.
        // Quando `promphp/prometheus_client_php` não estiver instalado, todas as
        // chamadas caem em log estruturado (sem lançar exceção).
        // Bound via contrato (MessagingMetricsContract) para facilitar mocking em testes.
        $this->app->singleton(MessagingMetricsContract::class, MessagingMetrics::class);

        // T092 (Fase 4 Lote J) — AuthMetrics: 4 métricas Prometheus do domínio Auth.
        // Mesma estratégia graceful do MessagingMetrics: degrada para Log::debug
        // quando o pacote Prometheus não está disponível (CI / dev sem exporter).
        $this->app->singleton(AuthMetricsContract::class, AuthMetrics::class);

        // T024 (Fase 5) — AgendaMetrics: 7 métricas Prometheus do domínio Agenda.
        // Mesma estratégia graceful — degrada para Log::debug sem o pacote Prometheus.
        $this->app->singleton(AgendaMetricsContract::class, AgendaMetrics::class);

        // T034 — Fase 4: BearerAuthContract → TokenIssuerService (singleton).
        // Injeta em LoginController, LogoutAllController e qualquer consumer que
        // resolva via contrato (facilita mocking em testes de feature).
        $this->app->singleton(BearerAuthContract::class, TokenIssuerService::class);

        // Wrapper do Stripe SDK — permite swap por mock em testes.
        // Só instancia o StripeClient real se a chave secreta estiver configurada.
        $this->app->singleton(StripeClientWrapper::class, function () {
            $secret = (string) config('cashier.secret');

            if ($secret === '') {
                $secret = 'sk_test_placeholder_for_non_stripe_calls';
            }

            return new StripeClientWrapper(new StripeClient($secret));
        });
    }

    /**
     * Bootstrap dos serviços da aplicação.
     */
    public function boot(): void
    {
        // T183 — Cashier com Tenant como modelo billable.
        Cashier::useCustomerModel(Tenant::class);

        $this->configureSentryScope();
        $this->configureTenantCachePrefix();
        $this->configureSpatieTeamId();
        $this->configureSuperAdminGate();
        $this->registerPolicies();

        // Fase 3 — T108: Observer para reabertura automática de conversa ao criar
        // mensagem inbound em conversa resolvida (NC-2).
        Message::observe(MessageObserver::class);

        // T034 — Fase 4: Detecta uso suspeito de Bearer token pós-autenticação.
        // `Laravel\Sanctum\Events\TokenAuthenticated` é disparado pelo guard Sanctum
        // em cada request autenticada via Bearer (Sanctum v4.3+).
        // O detector compara IP/UA com cache Redis (TTL 5min) e dispara `TokenUsoSuspeito`
        // se detectar troca de contexto em janela suspeita (NC-3 gate R1 mitigação).
        Event::listen(
            TokenAuthenticated::class,
            static function (TokenAuthenticated $event): void {
                $request = request();
                app(SuspiciousTokenUsageDetector::class)->detect(
                    $event->token,
                    (string) $request->ip(),
                    (string) $request->userAgent(),
                );
            }
        );

        // Fase 5 — Listeners auto-discovered via Laravel 11+ event discovery
        // (scan de app/Listeners/Agenda/* com type-hint do evento no método handle).
        // Listeners auto-registrados:
        //  - MoveCardToAgendadoColumn               → ConsultaCriada (FR-013)
        //  - DispatchConfirmationToInbox            → ConsultaConfirmacaoPendente (US-6.4)
        //  - EscalateCancellationOutsideWindowToInbox → CancelamentoSolicitadoForaDoPrazo (clarify nº 3)
        //  - EscalateRescheduleLimitExceededToInbox → LimiteDeReagendamentoExcedido (clarify nº 7)
        //  - OpenWaitlistOnCancellation             → ConsultaCancelada (clarify nº 8)
        //  - DispatchWaitlistOfferToInbox           → VagaAbertaNaListaDeEspera (clarify nº 8)
    }

    /**
     * Registra as policies da aplicação no Gate (T162 — OnboardingPolicy).
     *
     * A ability `manage-onboarding` é mapeada diretamente via Gate::define
     * porque a OnboardingPolicy não está associada a um Eloquent model.
     */
    protected function registerPolicies(): void
    {
        Gate::define('manage-onboarding', [OnboardingPolicy::class, 'manage']);

        Gate::policy(Invitation::class, InvitationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);

        // Fase 2 — CRM de Pacientes (T032 — spec § 2.4).
        Gate::policy(Paciente::class, PacientePolicy::class);
        Gate::policy(Anotacao::class, AnotacaoPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);
        Gate::policy(Convenio::class, ConvenioPolicy::class);
        Gate::policy(FunilColuna::class, FunilPolicy::class);

        // Fase 3 — Omnichannel Inbox (T076 + T119 + T154 + T239).
        Gate::policy(Channel::class, ChannelPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        // AssignmentPolicy usa Conversation como model (assign/transfer/viewAssignments)
        Gate::policy(AssignmentRule::class, AssignmentPolicy::class);
        Gate::policy(QuickReply::class, QuickReplyPolicy::class);

        // Fase 5 — Agenda de Consultas.
        Gate::policy(Professional::class, ProfessionalSchedulePolicy::class);
        Gate::policy(AppointmentType::class, AppointmentTypePolicy::class);
        Gate::policy(Appointment::class, AppointmentPolicy::class);
    }

    /**
     * Sincroniza o "team id" do Spatie Permission com o tenant resolvido
     * (T061 — Princípio II). Com `permission.teams = true` e
     * `team_foreign_key = 'tenant_id'`, o Spatie filtra roles/permissions
     * automaticamente pelo team id corrente.
     *
     *  - `TenantResolved` (middleware): seta team id = `$tenant->id`.
     *  - `Authenticated`: cobre o caso do usuário autenticado **fora**
     *    do middleware (CLI, jobs, testes que usam `$this->actingAs()`)
     *    — usa `$user->tenant_id` (NULL para Super Admin = team id null,
     *    o que faz o Spatie cair no caminho "global").
     */
    protected function configureSpatieTeamId(): void
    {
        Event::listen(TenantResolved::class, static function (TenantResolved $event): void {
            app(PermissionRegistrar::class)->setPermissionsTeamId($event->tenant->id);
        });

        Event::listen(Authenticated::class, static function (Authenticated $event): void {
            $user = $event->user;

            if (! is_object($user)) {
                return;
            }

            $tenantId = $user->tenant_id ?? null;

            app(PermissionRegistrar::class)->setPermissionsTeamId(
                $tenantId !== null && $tenantId !== '' ? (int) $tenantId : null
            );
        });
    }

    /**
     * Bypass total para `super-admin` (T061 — Princípio II + VII). Como
     * `super-admin` tem `tenant_id = NULL` (role global), o Spatie
     * resolve a role normalmente quando o team id é `null`. O Gate
     * `before` evita a necessidade de declarar policy para cada ability
     * cross-tenant.
     *
     * Exceções (Fase 2 — FR-038): Super Admin NÃO acessa dados clínicos
     * de pacientes — abilities do namespace `paciente.*` e classes do
     * domínio CRM caem na policy específica (que nega explicitamente
     * via `before` da policy).
     */
    protected function configureSuperAdminGate(): void
    {
        $crmDomainClasses = [
            Paciente::class,
            Anotacao::class,
            Tag::class,
            Convenio::class,
            FunilColuna::class,
        ];

        Gate::before(static function (Authenticatable $user, string $ability, array $arguments = []) use ($crmDomainClasses): ?bool {
            if (! method_exists($user, 'hasRole')) {
                return null;
            }

            if (! $user->hasRole('super-admin')) {
                return null;
            }

            // FR-038: Super Admin nunca enxerga dados de paciente.
            // Abilities `paciente.*` saem do bypass — deixa a policy decidir.
            if (str_starts_with($ability, 'paciente.') || str_starts_with($ability, 'lead.')) {
                return null;
            }

            // Abilities de policy (`viewAny`, `view`, `create`, …) podem
            // chegar com um Model do CRM como primeiro argumento — também
            // saem do bypass.
            $firstArg = $arguments[0] ?? null;
            if ($firstArg !== null) {
                $targetClass = is_object($firstArg) ? $firstArg::class : (is_string($firstArg) ? $firstArg : null);
                if ($targetClass !== null && in_array($targetClass, $crmDomainClasses, true)) {
                    return null;
                }
            }

            return true;
        });
    }

    /**
     * Aplica o prefix `paciente360:tenant:{id}:` no driver de cache
     * default sempre que um tenant é resolvido (T045 — Princípio II,
     * isolation by default).
     *
     * Implementação:
     *  - Listener no evento `TenantResolved` (disparado pelo middleware
     *    `ResolveTenant` após bind de `app('tenant')`).
     *  - `Config::set('cache.prefix', ...)` + `Cache::forgetDriver()`
     *    força o `CacheManager` a reconstruir o repositório com o novo
     *    prefix na próxima chamada a `Cache::*`.
     *  - O store `global` (configurado em `config/cache.php`) NÃO é
     *    afetado — chaves cross-tenant continuam isoladas pelo store,
     *    não pelo prefix.
     *
     * Nota: o reset do prefix entre tenants no MESMO processo (caso
     * Octane/queue worker hot) acontece via novo `TenantResolved` —
     * ou via `Cache::store('global')` para acessar o store sem prefix.
     */
    protected function configureTenantCachePrefix(): void
    {
        Event::listen(TenantResolved::class, static function (TenantResolved $event): void {
            Config::set('cache.prefix', "paciente360:tenant:{$event->tenant->id}:");

            // Esquece a instância default do cache para que o
            // `CacheManager` reconstrua com o prefix novo na próxima
            // resolução.
            Cache::forgetDriver(Config::get('cache.default'));
        });
    }

    /**
     * Popula o escopo do Sentry com `tenant.id` e `user.id` quando
     * disponíveis (Princípio V — Observabilidade).
     *
     * Estratégia:
     *  - Sem o pacote `sentry/sentry-laravel` resolvido no container,
     *    nada acontece (boot continua intocado para testes/CI sem DSN).
     *  - Quando um usuário é autenticado, escutamos
     *    `Illuminate\Auth\Events\Authenticated` para fixar `user.id` e
     *    `tenant.id` (extraído do próprio model autenticado).
     *  - Quando o middleware `ResolveTenant` (T050) dispara o evento
     *    string `tenant.resolved`, propagamos `tenant.id` e
     *    `tenant.slug` para o escopo — útil em rotas públicas
     *    pré-autenticação (cadastro, login) onde o tenant é resolvido
     *    pelo subdomínio antes de qualquer login.
     *
     * Os closures fazem `function_exists('Sentry\\configureScope')` para
     * tolerar ausência do SDK em runtime sem quebrar o request.
     */
    protected function configureSentryScope(): void
    {
        if (! $this->app->bound('sentry')) {
            return;
        }

        Event::listen(Authenticated::class, static function (Authenticated $event): void {
            if (! function_exists('Sentry\\configureScope')) {
                return;
            }

            \Sentry\configureScope(function (Scope $scope) use ($event): void {
                $user = $event->user;

                $scope->setUser([
                    'id' => (string) $user->getAuthIdentifier(),
                ]);

                $tenantId = $user->tenant_id ?? null;
                if ($tenantId !== null && $tenantId !== '') {
                    $scope->setTag('tenant.id', (string) $tenantId);
                }

                // T094 (Fase 4 Lote J) — Auth Sanctum context.
                // Quando autenticado via Bearer (PersonalAccessToken), adiciona
                // ID do token + 8-char prefix do plain text para correlação
                // sem leak da chave (FR-023 / Princípio I LGPD).
                if (method_exists($user, 'currentAccessToken')) {
                    $token = $user->currentAccessToken();

                    if ($token instanceof PersonalAccessToken) {
                        $scope->setTag('auth.token_id', (string) $token->id);
                        $scope->setTag('auth.token_name', (string) $token->name);
                    }
                }
            });
        });

        Event::listen('tenant.resolved', static function ($tenant): void {
            if (! function_exists('Sentry\\configureScope')) {
                return;
            }

            \Sentry\configureScope(function (Scope $scope) use ($tenant): void {
                if (is_object($tenant) && isset($tenant->id)) {
                    $scope->setTag('tenant.id', (string) $tenant->id);
                }

                if (is_object($tenant) && isset($tenant->slug)) {
                    $scope->setTag('tenant.slug', (string) $tenant->slug);
                }
            });
        });

        // T272 — Sentry context para eventos de messaging.
        //
        // Tags adicionadas ao escopo quando um evento de messaging é disparado:
        //  - `messaging.conversation_id` — ID da conversa envolvida
        //  - `messaging.channel_id`      — canal de origem
        //  - `messaging.message_id`      — ID da mensagem (apenas para MensagemRecebida)
        //
        // Tags têm cardinalidade controlada — IDs numéricos são seguros no Sentry.
        // Breadcrumbs registram o evento para correlação de erros no timeline.

        Event::listen(MensagemRecebida::class, static function (MensagemRecebida $event): void {
            if (! function_exists('Sentry\\configureScope') || ! function_exists('Sentry\\addBreadcrumb')) {
                return;
            }

            \Sentry\configureScope(function (Scope $scope) use ($event): void {
                $scope->setTag('messaging.conversation_id', (string) $event->conversation->id);
                $scope->setTag('messaging.channel_id', (string) $event->conversation->channel_id);
                $scope->setTag('messaging.message_id', (string) $event->message->id);
            });

            \Sentry\addBreadcrumb(new Breadcrumb(
                level: Breadcrumb::LEVEL_INFO,
                type: Breadcrumb::TYPE_DEFAULT,
                category: 'messaging',
                message: 'Mensagem inbound recebida',
                metadata: [
                    'message_id' => $event->message->id,
                    'conversation_id' => $event->conversation->id,
                    'channel_id' => $event->conversation->channel_id,
                    'content_type' => $event->message->content_type,
                ],
            ));
        });

        Event::listen(ConversaCriada::class, static function (ConversaCriada $event): void {
            if (! function_exists('Sentry\\configureScope') || ! function_exists('Sentry\\addBreadcrumb')) {
                return;
            }

            \Sentry\configureScope(function (Scope $scope) use ($event): void {
                $scope->setTag('messaging.conversation_id', (string) $event->conversation->id);
                $scope->setTag('messaging.channel_id', (string) $event->conversation->channel_id);
            });

            \Sentry\addBreadcrumb(new Breadcrumb(
                level: Breadcrumb::LEVEL_INFO,
                type: Breadcrumb::TYPE_DEFAULT,
                category: 'messaging',
                message: 'Conversa criada',
                metadata: [
                    'conversation_id' => $event->conversation->id,
                    'channel_id' => $event->conversation->channel_id,
                    'tenant_id' => $event->conversation->tenant_id,
                ],
            ));
        });
    }
}
