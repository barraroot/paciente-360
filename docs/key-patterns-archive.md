# Key Patterns — Arquivo (Fases 2–11)

Detalhe técnico completo das fases já entregues e estáveis. Movido do CLAUDE.md em 2026-05-25 para reduzir o contexto carregado por sessão. As Fases 12–14 (recentes) permanecem no CLAUDE.md. Consulte aqui quando mexer em código dessas fases.

---

## CRM Pacientes (Fase 2) — Key Patterns

When working on CRM Pacientes features, remember these critical patterns:

1. **`pg_trgm + unaccent` enabled in PostgreSQL**
   - Buscas por nome/telefone usam `% similarity` com índice GIN composto `(tenant_id, campo_trgm)`.
   - `unaccent()` não é IMMUTABLE — use wrapper `immutable_unaccent(text)` em colunas GENERATED para evitar índices inválidos.

2. **Cast `AsJsonArray` padrão para JSONB multi-valor**
   - Use em colunas JSONB como `pacientes_origem_ids`, `checkpoint`, `snapshot_pre_merge`, `payload` de eventos.
   - Aplicado automaticamente em `MesclagemPaciente`, `Importacao`, `EventoTimeline`.

3. **Listener `RegistraEventoTimelineListener` projeta eventos para timeline**
   - Escuta qualquer `Auditable` cujo `auditableModel()` retorna Paciente/Anotacao/Tag.
   - Grava em `eventos_timeline` automaticamente (além de `audit_logs`).
   - Bind em `EventServiceProvider`.

4. **Abilities granulares `paciente.note.view:{tipo}` controlam visibilidade**
   - 4 tipos: `geral`, `clinica`, `comportamental`, `financeira`.
   - `AnotacaoPolicy::view()` retorna falso se o user não tem ability para o tipo da anotação.
   - Aplicado em `PacientePolicy`, `AnotacaoPolicy`, confirmado em T030.

5. **Event `ProfessionalDeactivated` dispara reatribuição de pacientes (T260)**
   - Observer no `Professional.boot()` detecta `is_active: true → false`.
   - Listener cria `TarefaReatribuicao` com lista de pacientes órfãos.
   - Job `ReassignOrphansJob` (extends `TenantAwareJob`) atualiza `profissional_responsavel_id = null`.

## Token Auth (Fase 4) — Key Patterns

When working on auth features post-Fase 4, remember:

1. **API tenant é stateless via Bearer Sanctum**
   - Endpoints autenticados exigem `Authorization: Bearer paciente360_<token>` + `X-Tenant-Slug: <slug>` (FR-011 triple-check).
   - Filament admin permanece cookie-session em domínio separado (`crm.com.br`), NÃO compartilha auth com a API tenant.
   - `users.email` é UNIQUE global (migration `2026_05_13_000001`) — permite resolver tenant via lookup direto no login.

2. **`User::guardName()` pina Spatie no guard `'web'`**
   - `Auth::shouldUse('sanctum')` (chamado pelo middleware `auth:sanctum`) muta `config('auth.defaults.guard')`. Sem o pin, Spatie buscaria permissions com `guard='sanctum'` e falharia silenciosamente (permissions seedadas com `guard='web'`).
   - Pinning resolve uma vez para todos os controllers Bearer-authenticated.

3. **Middleware `tenant.slug` em rotas `/auth/*`**
   - `EnsureTenantSlugHeader` (alias `tenant.slug`) — 400 se header ausente, 403 se mismatch com `$user->tenant_id`.
   - Allow-list: `api/v1/auth/login` apenas (não exige header — lookup por email).
   - Para `/inbox/*` e demais rotas API, ainda não aplicado (rollout adiado — afetaria ~227 callers).

4. **Testes legados usam `Sanctum::actingAs($user, ['*'])`**
   - Comando `tests:migrate-actingas-to-sanctum --apply` migrou 120 statements standalone. Chained calls (`$this->actingAs($u)->getJson(...)`) deliberadamente preservados.
   - Fallback `sanctum.guard = ['web']` mantido em `config/sanctum.php` para não quebrar chains até migração manual completa.

5. **`Sanctum::actingAs` + Spatie permissions**
   - Em setUp de testes, usar `Sanctum::actingAs($user, ['*'])` para preservar a instância com cache de roles do Spatie carregado. `$user->createToken()` força reload do DB sem o cache → channel callbacks com `$user->can(...)` podem falhar.

6. **CSP estrita configurável via `config/csp.php`**
   - `connect-src` inclui Reverb WSS + S3 media + API host. Override via env `CSP_REVERB_HOST` / `CSP_MEDIA_HOST` / `CSP_API_HOST`.
   - Production: nonce gerado por request, sem `unsafe-inline`/`unsafe-eval`. Local/test: permissivo para Vite HMR.

7. **Token retention 90d (`auth:tokens-purge-expired`)**
   - Schedule diário 03:00 BRT em `routes/console.php`. Purga `personal_access_tokens` com `expires_at < now()-90d`.
   - 4 métricas Prometheus em `AuthMetrics`: `auth_login_total{result}`, `auth_token_emitido_total`, `auth_token_revogado_total{motivo}`, `auth_active_tokens`.

## Agendamento (Fase 5) — Key Patterns

When working on agenda features post-Fase 5, remember:

1. **PARTIAL UNIQUE em `appointments` é o gate atômico de race condition**
   - `CREATE UNIQUE INDEX app_active_slot_unique ON appointments (tenant_id, professional_id, starts_at) WHERE status IN ('scheduled', 'confirmed')` (FR-011a / SC-008).
   - Status terminais (`canceled, realizada, nao_realizada, concluida_sem_registro`) ficam fora — slot consumido/passado pode ser reusado em datas futuras.
   - `reschedule` PRESERVA status (clarify nº 7) — sem 'reagendada' no enum.

2. **`SlotReservation` é reserva pessimista soft com TTL diferenciado**
   - PARTIAL UNIQUE `(tenant_id, professional_id, starts_at) WHERE released_at IS NULL` impede 2 reservas ativas no mesmo slot.
   - TTL 5min user / 2min IA (configurável em `tenant.settings.agenda.slot_reservation_ttl_*_minutes`).
   - Cleanup cron `agenda:cleanup-expired-reservations` (everyMinute) marca `release_reason='expired'`.
   - Defesa em profundidade — gate final é o UNIQUE em `appointments` (FR-011a).

3. **Sub-calendário Google tenant-scoped (clarify nº 15)**
   - `CalendarSyncAccount` UNIQUE(`tenant_id`, `professional_id`) — mesma conta Google em 2 tenants gera 2 rows com `google_calendar_id` distintos.
   - Sub-cal criado automaticamente no callback OAuth: `Paciente360 — {Tenant.nome}`.
   - TODA chamada Google API usa `calendarId={sub_cal_id}` — eventos do tenant A invisíveis ao polling do tenant B.
   - Gate: `CrossTenantGoogleSyncTest` valida ExternalCalendarBusy isolado.

4. **Payload Google sem PII clínica (FR-038/038a)**
   - `GoogleCalendarSyncService::buildEventBody()` produz APENAS:
     - `summary`: `"Consulta — {Profissional.nome}"` (fixo, sem nome paciente / CPF / convênio)
     - `description`: `"Agendamento via {Tenant.nome}"` (genérico)
     - `start.timeZone` / `end.timeZone`: IANA do profissional
   - Gate LGPD: `GoogleEventPayloadLgpdTest` (assertStringNotContainsString para Maria Souza, CPF, "Cirurgia", "dor no peito").

5. **Listeners auto-discovered Laravel 11+ — NÃO registrar manualmente**
   - Laravel 11+ scaneia `app/Listeners/` e auto-registra listeners via type-hint do método `handle($event)`.
   - Registrar via `Event::listen()` em AppServiceProvider DUPLICA execução (descoberto em Lote F: lista de espera notificava 2x — fix removeu registrações manuais).
   - Padrão: criar listener com `handle(EventClass $event)` typed → discovery cuida do resto.

6. **TZ tenant default + override profissional + UTC interno (clarify nº 13)**
   - `tenants.timezone` é fonte; `professionals.timezone` é override nullable.
   - `TimezoneResolverService::forProfessional()` retorna IANA correto.
   - DB: tudo `timestamptz UTC`. API REST: ISO 8601 com offset + envelope `timezone_display` IANA.
   - Mensagens ao paciente: `IanaTimezoneCity::format("14:00", "America/Sao_Paulo")` → `"14:00 (horário de São Paulo)"`.

7. **Cron schedule (6 commands em `routes/console.php`)**
   - `agenda:cleanup-expired-reservations` — everyMinute (TTL slot reservations)
   - `agenda:expire-waitlist-notifications` — everyMinute (clarify nº 8 — re-notifica próximo)
   - `agenda:dispatch-confirmations` — every5min (T-24h/T-2h/retry/escalation)
   - `agenda:auto-close-stale-appointments` — daily 00:30 BRT (clarify nº 14 — janela 7d)
   - `agenda:google-poll-fallback` — every5min (R3 — cobre watch channel expirado)
   - `agenda:google-renew-watch-channels` — daily 02:00 BRT (R3 — renova antes TTL ~7d)

8. **`ConfirmationDispatch.status='pending_manual'` ≠ `Appointment.status`**
   - Quando T-15min sem resposta OU paciente sem canal → `ConfirmationDispatch.status='pending_manual'` + emit `ConsultaPendenteContatoManual` (Fase 3 cria task na inbox).
   - **`Appointment.status` permanece `scheduled`** — desambiguado em FR-019b/FR-024 (analyze A1).

9. **Stubs Google API em `GoogleCalendarApiClient`**
   - Wrapper testável — métodos REAIS (createSubCalendar, insertEvent, watchChannel, etc.) marcados como TODO.
   - Em produção com `google/apiclient` instalado: implementar passando o pacote.
   - Tests (incl. CrossTenantGoogleSyncTest, GoogleEventPayloadLgpdTest) usam stubs — não fazem requests reais.

10. **`Appointment.notes` é encrypted via cast** (Princípio I)
    - Cast `'notes' => 'encrypted'` aplica `Crypt::encryptString` antes de persistir.
    - Mesmo padrão para `CalendarSyncAccount.encrypted_access_token` / `encrypted_refresh_token`.

11. **UX polish Fase 6 (006-agenda-ux-polish) — Padrões reutilizáveis**
    - **Modal a11y padrão**: `Teleport to="body"` + `role=dialog` + `aria-modal="true"` + `aria-labelledby` + focus trap Tab/Shift+Tab + `@keydown.esc.prevent="close"` + overlay click fecha + bottom-sheet `items-end sm:items-center` em mobile. Ref: `AppointmentFormModal.vue` / `RescheduleConfirmModal.vue`.
    - **Toast pattern local** (sem lib): `const toast = ref(null)` + `showToast(msg, type)` + `setTimeout 5000` + `role=alert aria-live=assertive`. Replicado em AppointmentTypesPage, ScheduleConfigPage, CalendarSyncPage.
    - **Popover inline para confirmações curtas** (substitui `confirm()` / `prompt()`): `ref(false)` para controle + `aria-expanded` no trigger + `aria-controls` no painel + Esc fecha via keydown local. Ex.: `AttendanceMarkButton.vue` — painel "Não realizada" com textarea + popover de reversão.
    - **Proibido**: `confirm()`, `prompt()`, `alert()` nativos em qualquer componente novo — todos inacessíveis por leitores de tela e bloqueiam tab-order.
    - **Confirmação destrutiva**: sempre modal descritivo com nome/impacto do que será deletado — nunca só "Tem certeza?".
    - **Formatação moeda**: `new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)`. Data relativa: `DateTime.fromISO(iso).toRelative({ locale: 'pt-BR' })` via Luxon (já no bundle).

## Receituários (Fase 7) — Key Patterns

When working on prescription features post-Fase 7, remember:

1. **`PrescriptionType` define a regra regulatória (Portaria 344/98)**
   - `controlled` (Listas A) → validade fixa 30d + EXATAMENTE 1 item (trigger DB `enforce_controlled_single_item`) + mascaramento + audit em cada visualização.
   - `special` (Listas B) → validade fixa 30d.
   - `common` (Lista C ou sem controle) → `duration_days ∈ {30, 60, 90, 180}` (CHECK constraint server-side).
   - CHECK `chk_prescription_validity_by_type` enforça regra no DB; `StorePrescriptionRequest` é defesa em profundidade.

2. **Mascaramento de controladas via `ControlledPrescriptionMaskingService`**
   - Receita `type=controlled` retorna `PrescriptionMasked` (omite `items`/`notes`) para qualquer user sem ability `prescription.view_controlled`.
   - Emissor + Admin Clínica veem completo + emitem evento `PrescricaoControladaVisualizada` (audit em `audit_logs`).
   - **Ponto único de emissão**: `PrescriptionResource::toArray()` — evita duplicação em coleções.

3. **Global scope `withControlledIfAble` em `Prescription::booted()`**
   - Quando user não tem `prescription.view_controlled`: filtra receitas `controlled` cujo `professional_id != $user->id` antes do query DB.
   - Isto + mascaramento no Resource = duas camadas de defesa (Princípio I + defense in depth).

4. **Cadência de alertas D-15/D-7/D-1 — Idempotência dual layer**
   - DB UNIQUE `(prescription_id, alert_type)` + Redis lock `prescription_alert:{pid}:{type}:{date}` TTL 25h (defense in depth).
   - `PrescriptionAlertIdempotencyKey::for($pid, $alertType, $date)` gera chave Redis (padrão Fase 5 commands).
   - Cron `prescriptions:process-alerts` daily 06:00 BRT + `prescriptions:expire-active` daily 00:30 BRT (`withoutOverlapping()`).
   - Checkpoint passado na criação → `status=skipped` com `skip_reason='checkpoint_past_at_creation'` (não tenta disparar retroativo).

5. **`ContainsNoClinicalData` marker interface — Gate LGPD por reflection**
   - Qualquer evento consumido pela IA Matricial implementa `App\Support\Lgpd\ContainsNoClinicalData` (marker sem métodos).
   - `PrescriptionEventPayloadLgpdTest` valida via reflection que `ReceitaProximaDoVencimento` tem EXATAMENTE 7 props: `prescriptionId, patientId, professionalId, professionalName, daysUntilExpiry, prescriptionType, defaultAppointmentTypeId`.
   - **Qualquer field clínico (medication_name/posology/notes) quebra o gate** — adicionar nova prop exige revisão LGPD obrigatória.
   - `PrescriptionForAiResource` projeta os mesmos 7 campos no endpoint `GET /ai/prescriptions/{id}/context`.

6. **Opt-out paciente via `PatientProfessionalPreference`**
   - `suppress_renewal_notifications` boolean por `(patient_id, professional_id)` — UNIQUE composto.
   - `DispatchPrescriptionAlertViaMessaging` lê a preferência; se `suppress=true` → alert vira `skipped` com `skip_reason='recipient_opted_out'`. Evento `ReceitaProximaDoVencimento` ainda é emitido — apenas envio externo é suprimido.

7. **Debounce 4h por destinatário via Redis** (FR-016 / Q4d)
   - Cache key `messaging_debounce:prescription_alert:{patient_id}:{alert_type}` TTL 14400s.
   - `Redis::set($key, 1, 'EX', 14400, 'NX')` → se já existe → `skip_reason='debounced'`.

8. **Renovação via `prescription_renewals` (junção explícita)**
   - UNIQUE parcial `original_prescription_id WHERE renewed_prescription_id IS NOT NULL` impede duas renovações concluídas da mesma origem.
   - `RenewPrescriptionService::complete()` transita original → `superseded` + emite `ReceitaRenovada` → listener `CancelAlertScheduleOnRenewal` cancela alerts pending.
   - `StorePrescriptionRequest` aceita `renewed_from_id` nullable; `PrescriptionService::create()` chama `complete()` na mesma transação.
   - Política: `canRenew = status=active AND expires_at <= today+30d AND não já renovada`. Inelegível → 422 `prescription_not_eligible_for_renewal` com `reason` específico.

9. **Versionamento de PDF path-based** (research §2)
   - Path `prescriptions/{tenant_id}/{prescription_id}/v{n}.pdf` — versão atual em `pdf_version` na DB.
   - Substituição preserva `v0.pdf` no S3 (não usa S3 native versioning — portabilidade entre disks).
   - URL assinada TTL 15min via `PrescriptionSignedUrlService::sign()` + audit log de emissão.
   - Job semanal `prescriptions:purge-old-pdfs` mantém últimas 5 versões — controladas preservadas TODAS dentro da janela de retenção.

10. **Filament super-admin read-only para suporte** (research §7.4)
    - `app/Filament/Resources/Prescriptions/PrescriptionResource.php` — `withoutGlobalScopes()` para enxergar cross-tenant.
    - Apenas `ViewAction` (sem create/edit/delete). Audit log `super_admin.prescription.viewed` no boot do componente.
    - Acessível em `crm.com.br/admin` (cookie session Fase 4).

11. **Métricas Prometheus em `PrescriptionMetrics`**
    - `prescription_alerts_dispatched_total{tenant, alert_step, status}`
    - `prescription_alerts_blocked_total{reason, tenant}` (`no_template`, `no_channel`, `no_conversation`)
    - `prescription_alerts_idempotency_hits_total`
    - `prescription_alerts_processed_total`
    - `prescription_renewals_initiated_total{initiated_by, tenant}`
    - `prescription_pdfs_uploaded_total{status}`
    - `prescription_signed_urls_emitted_total{tenant}`
    - `prescription_csv_exports_total{tenant, has_controlled}`
    - `prescription_controlled_access_denied_total{tenant, perfil}` (alerta Sentry > 10 em 5min = scan).

12. **DEFERRED ao final da Fase 7** (documentados nos commits dos lotes C e D)
    - **InboxTask real**: `EnqueueInboxTaskOnAiRenewal` e fallback em `DispatchPrescriptionAlertViaMessaging` usam `Log::warning` + métrica. Integração com `ConversationService::createForPatient()` da Fase 3 ainda não disponível (Conversation precisa `channel_id` + `external_thread_id`, modelo de inbox interna ainda não desenhado).
    - **`MessageDispatchService::send()` real**: lookup de Conversation por paciente não existe — dispatcher atualiza `alert.status='dispatched'` diretamente.
    - **S3 real delete** em `PurgeOldPrescriptionPdfVersionsJob`: stub `Log::info` por enquanto.
    - **Smoke staging E2E**: 5 cenários do quickstart documentados em `docs/qa/smoke-fase7-prescriptions.md` — aguardando infra staging com módulo habilitado.
    - **Sentry alerts**: contadores Prometheus prontos; rules de alerting precisam ser configuradas em prod.


## Finalização (Fase 8) — Key Patterns

When working on features across Privacy/SuperAdmin/Campaigns/Integrations/Reports modules post-Fase 8, remember:

1. **`ConsentFinalidade::Integracoes` é o gate de PII em payload externo (Q17)**
   - Adicionado em migration `2026_05_25_000000_add_integracoes_to_consent_finalidade_enum.php` (ALTER TYPE).
   - `WebhookDispatcher::applyMasking()` chama `ConsentService::hasGranted($pacienteId, ConsentFinalidade::Integracoes)`. Sem granted → `paciente.id = '<consent_withheld>'` + outros campos removidos.
   - `PatientPublicResource` (API pública) aplica o mesmo gate.
   - PARTIAL UNIQUE `(patient_id, finalidade) WHERE state='granted'` em `consent_records` enforce 1 consentimento ativo por finalidade.

2. **Catálogo Q17 = EXATAMENTE 13 eventos no `BroadcastDomainEventToWebhooksListener::EVENT_CATALOG`**
   - Agenda 4 (Criada/Confirmada/Cancelada/Reagendada) + Pacientes 2 + Messaging 2 + Prescrições 2 (controladas mascaradas) + Campanhas 1 + Privacidade 2.
   - Subscriber registrado via `Event::subscribe()` em `EventServiceProvider::boot()` — NÃO usa auto-discovery (evita duplicação Fase 5 bug).
   - Gate test `WebhookCatalogCoverageTest` valida `count === 13` + dot-notation `<recurso>.<acao>` + classes existem via reflection. Adicionar evento ao catálogo exige atualizar este gate.

3. **HMAC SHA-256 + SSRF defense via `UrlGuard`**
   - `HmacSigner::sign($payload, $secret)` → `sha256=<hex>`. Verify usa `hash_equals` (timing-safe).
   - Header outbound: `X-Paciente360-Signature: sha256=...` + `X-Paciente360-Event` + `X-Paciente360-Event-Id` + `X-Paciente360-Correlation-Id`.
   - `UrlGuard::assertSafeOutboundUrl()` bloqueia RFC 1918 (10/8, 172.16/12, 192.168/16), loopback (127/8, ::1), link-local (169.254/16), CGN (100.64/10), `.local/.internal/.test/.invalid`, HTTP em produção (permite em local/test para Stripe simulator).
   - **NÃO faz DNS resolution** — defesa em profundidade adicional fica no Guzzle client (verify TLS + protocols estritos).

4. **Retry policy Q16: 30s, 2min, 10min, 1h, 6h (5 tentativas) → DLQ**
   - `DispatchWebhookJob::tries = 1` (controle manual via `next_attempt_at`, NÃO via Laravel automatic retries).
   - Após esgotar → `MoveToDeadLetterJob` move para `webhook_dead_letter` com `expires_at=now()+30d`.
   - DB UNIQUE `(webhook_endpoint_id, event_id)` em `webhook_deliveries` enforça idempotência.
   - Retention DLQ via cron `integrations:purge-expired-dlq` (daily 03:00 BRT).

5. **API Pública resolve tenant pelo TOKEN, NUNCA por URL/header**
   - Trait `ResolvesApiPublicTenant` em `app/Http/Controllers/Api/V1/Public/Concerns/` — `tenantId(Request)` lê de `$user->tenant_id` (Sanctum) ou `oauth.tenant_id` (OauthAuthenticator attribute).
   - Defesa contra cross-tenant attacks (Princípio II).
   - Recursos fora do escopo Q14 retornam **404** (não 401 — não revela existência) — `PublicApiScopeRestrictionTest` valida.

6. **Controladas (Portaria 344/98) SEMPRE mascaradas via API pública e webhooks**
   - `PrescriptionPublicResource`: se `type='controlled'`, omite `items` e `notes`, adiciona `masked=true` e nota explicativa.
   - `WebhookDispatcher::applyMasking()`: idem para `event_type` que começa com `prescricao.`.
   - **Defesa em profundidade** — não confia em scope do token: independente de `prescriptions.read_controlled`, o resource mascara.
   - `R-8-4` gate: `PublicApiControlledMaskingTest`.

7. **Idempotency-Key NFR-9 (24h dedup)**
   - POST `/api/public/v1/patients` e `/appointments` aceitam header `Idempotency-Key`.
   - Cache key: `api_public:idempotency:{tenant_id}:{resource}:store:{key}` TTL 24h.
   - Replay retorna 201 + header `Idempotency-Replayed: true` com mesmo body original.

8. **OAuth 2.0 Client Credentials gated por `finalization.oauth_enabled` (Q18)**
   - Default `false` — `OauthClientService::createClient()` lança `RuntimeException('oauth_disabled')`.
   - Quando habilitado, `tenant_oauth_clients` table armazena `client_secret_hash` (SHA-256). Plaintext retornado APENAS no create.
   - **Stub JWT-like** em formato `stub.<base64-payload>.stub` — produção exige Passport real (composer require lazy).
   - `OauthAuthenticator` middleware decodifica payload + injeta `oauth.tenant_id` no request attributes.

9. **Rate limit por token + cap IP em `ApiPublicRateLimiter`**
   - Token: `plan.api_rate_limit_per_minute` (default 60, varia por plano via PlanVersion snapshot).
   - IP: `finalization.api_public_ip_hard_cap_per_minute` (default 10000) — cap anti-DDoS global.
   - Headers RFC 6585: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After` em 429.

10. **`tenant_suspended` retorna 503 na API pública (não 403)**
    - `EnsureApiPublicTenantNotSuspended` middleware ≠ `EnsureTenantNotSuspended` interno (que devolve 403).
    - 503 sinaliza ao integrador que o problema é temporário do tenant, não da API — ele pode retentar quando regularizado.
    - Resolve tenant via: `app('tenant')` → `$user->tenant` → `oauth.tenant_id` attribute.

11. **Pseudonimização dual layer para IA (Q29)**
    - **Layer 1 (design-time)**: marker interface `App\Support\Lgpd\ContainsNoClinicalData` — todos eventos consumidos pela IA devem implementar. CI gate `EventsForAiPseudonymizationTest` valida via reflection.
    - **Layer 2 (runtime)**: `PseudonymizationAuditor` audita semanalmente prompts via cron `privacy:audit-pseudonymization`. Resultado em `pseudonymization_audits` table.
    - `PiiScrubber` (T291) aplicado ao Sentry `before_send` global — mascarara CPF/email/telefone/RG/SUS em mensagens de exceção e breadcrumbs.

12. **Q26 — Mapa de anonimização explícito**
    - Cada coluna `anonymizable` está documentada em `docs/lgpd/privacy-operations.md` § 2.
    - `ForgettingExecutor` aplica updates atômicos em uma transação.
    - **Preservadas por obrigação legal**: receitas `controlled` (Portaria 344/98 — 5y), `audit_logs` (Princípio VII — 5y), `appointments.starts_at/ends_at` (registro contábil).
    - Gate test `MapaAnonimizacaoTest` valida que cada coluna marcada foi realmente zerada/redigida.

13. **Super Admin Impersonate — Gate 7 (audit obrigatório)**
    - Banner sticky `ImpersonateBanner.vue` em `<App>.vue` polling 60s para validar sessão ativa.
    - Cada tela visitada durante impersonate gera `super_admin.screen.visited` audit_log via `ImpersonateScreenAuditTrigger` middleware.
    - Sessão sem ≥1 audit é flagged como anomalia (provavelmente bot scrape).
    - 4 tipos de anomalias monitoradas: `mass_data_export`, `unusual_impersonate`, `controlled_prescription_scan`, `webhook_delivery_failure_spike`.

14. **Estratégia Q9: agregações ≥24h, queries live ≤24h (Relatórios)**
    - `ExecutiveDashboardService::shouldUseAggregations()` decide por janela.
    - `metric_aggregations` table com PARTIAL UNIQUE composto `(tenant_id, metric_name, period, period_start, COALESCE(dimensions, '{}'::jsonb))` para upsert idempotente.
    - Cron `reports:aggregate-hourly` (hourlyAt :05) chama `MetricAggregator::aggregateDailyForTenant()` para 8 métricas (leads_by_channel, conversion_rate, no_show_rate, estimated_revenue, response_time_first_p95, ai_autonomous_resolution_rate, occupancy_by_professional, top_procedure_types).
    - `aggregation_lag_seconds` no envelope > 7200 → banner stale na SPA (R-8-5).

15. **Q13: Escopo por perfil em Relatório Clínico**
    - `ClinicalReportService::professionalScopeFor(User)` retorna `$user->id` se `hasRole('medico') AND ! hasRole('admin-clinica')`, caso contrário `null`.
    - Médico vê apenas própria agenda; Admin Clínica vê tenant inteiro.
    - Resource expõe `scoped_professional_id` no envelope para o front renderizar título correto.

16. **`finalization.php` config como single source of truth**
    - Lote D: `oauth_enabled`, `webhook_max_retries`, `webhook_dlq_retention_days`, `webhook_retry_backoff_seconds`, `webhook_http_timeout_seconds`, `api_public_ip_hard_cap_per_minute`, `webhook_max_endpoints_default`.
    - Lote E: `report_aggregation_threshold_hours`, `report_max_window_months`.
    - Overrides via env (FINALIZATION_*) — defaults conservadores.

17. **DEFERRED ao final da Fase 8** (Phase 8 Polish documentado em `docs/qa/*` e `docs/lgpd/*`)
    - **Constitution Gates execução real (T287)**: testes codificados, requer Sail rodando → `docs/qa/gates-fase8-final.md`.
    - **Suite full execution (T288)**: validar ~1517 tests verdes → requer Sail.
    - **OpenAPI Scribe (T292)**: `scribe:generate` requer Sail + tags @apiResource manuais nos 6 public controllers.
    - **Sentry tags validation (T290)**: tags codificadas em `configureSentryScope()` — validação visual no painel Sentry.
    - **Smoke staging E2E (T296-T297)**: 10 cenários documentados em `docs/qa/smoke-fase8-staging.md`.
    - **DPO approval formal (T298)**: template em `docs/lgpd/dpo-approval-fase8.md` aguardando revisão jurídica.
    - **Passport instalação concreta**: gated por `FINALIZATION_OAUTH_ENABLED=true` em produção enterprise.
    - **InboxTask real** (herdado da Fase 7): `EnqueueInboxTaskOnAiRenewal` ainda usa `Log::warning` — aguardando `ConversationService::createForPatient()`.
    - **S3 real delete** (herdado): stub `Log::info` em jobs de purga.

## App Shell (Fase 9) — Key Patterns

When working on `/panel/*` routes post-Fase 9, remember:

1. **Rota pai `/panel` com nested children renderiza `AppShell` uma única vez**
   - `routes/index.js`: `/panel` tem `component: AppShell` e `children: panelChildren` (38 rotas).
   - Sidebar/Topbar montam apenas no primeiro acesso ao painel; navegação interna só troca `<router-view>`.
   - **Não declarar `/panel/*` como rota raiz** — quebra o reuso do chrome e força remount.
   - `/panel/onboarding` é IRMÃ (não filha) — fullscreen sem chrome por design.

2. **Navegação por permissões: única source of truth em `config/navigation.js`**
   - Árvore canônica estática (10 grupos + items). Cada entry com `routeName` + `ability` (ou `anyOf`).
   - `useNavigation()` faz filter em runtime contra `auth.permissions`. Grupos com 0 children visíveis somem inteiros.
   - **Para adicionar item novo na sidebar**: 1) entry em `navigation.js` com `routeName` + `ability`; 2) i18n key em `pt-BR.json` (`layout.sidebar.*`); 3) `meta.title` na rota filha em `router/index.js`.

3. **Preferências de UI: `localStorage` escopado por `tenant_slug + user_id`**
   - Chave única: `app-shell:preferences:v1` com JSON aninhado `{ [tenantSlug]: { [userId]: { sidebarMode, expandedGroups } } }`.
   - **Gate Princípio II**: NUNCA usar chave plana — multi-tenant cross-leak. `useShellPreferences` lê de `auth.tenant.slug + auth.user.id` reativamente.
   - Fallback robusto: localStorage indisponível ou JSON corrompido → defaults silenciosos. Operações nunca lançam.

4. **Breakpoints reativos via `useBreakpoint`**
   - 3 refs: `isMobile` (< 768px), `isTablet` (768–1023px), `isDesktop` (≥ 1024px).
   - Implementação via `useMediaQuery` do `@vueuse/core` (já dep).
   - AppShell tem watcher `isMobile` que fecha drawer ao cruzar para desktop (FR-022).

5. **Drawer mobile: `<Teleport to="body">` + focus trap próprio + Esc/click-outside**
   - `MobileDrawer.vue` reusa `Sidebar mode="expanded"` internamente (DRY).
   - `useShellFocusTrap` — implementação manual ~80 linhas; alternativa para `@vueuse/integrations` que requer 2 deps a mais.
   - Q1 clarification: drawer fecha **imediatamente** ao clicar item; navegação ocorre em paralelo.

6. **Document.title via `router.afterEach` + fallback estático**
   - Lê `to.meta.title` (i18n key, string literal, ou função); formata `{tenantName} — {pageTitle}`.
   - Fallback: `findLabelKeyForRoute(name)` faz lookup estático em `NAVIGATION` (sem usar `useNavigation()` fora de setup context).
   - Topbar exibe o mesmo título contextual entre tenant name e ícones à direita.

7. **UserMenu: logout fail-safe via `auth.logout() → router.push('auth.login')`**
   - Em erro de rede, ainda chama `auth.reset()` e redireciona — token Bearer pode estar inválido de qualquer forma (princípio VII).
   - Dropdown em `<Teleport to="body">` para escapar overflow/transform do parent.

8. **Heroicons SVG inline em componente único `HeroIcon.vue`**
   - Switch por `name` prop — 15 ícones (~15 KB bundle). Sem dep nova de pacote (research R10).
   - Adicionar ícone novo = adicionar bloco `v-else-if="name === 'foo'"` no componente.

9. **i18n: `pt-BR.json` (SPA) ≠ `lang/pt_BR/*.php` (backend)**
   - SPA usa JSON único em `resources/js/i18n/pt-BR.json`.
   - Bloco `layout.*` adicionado com `sidebar`, `topbar`, `user_menu`, `drawer`, `empty_state`, `panel_home`.
   - Backend `lang/pt_BR/layout.php` espelha apenas para mensagens de response (não usado pelo Vue I18n).

10. **Empty state quando user não tem nenhuma permission de módulo**
    - `useNavigation().isEmpty` true → AppShell substitui `<router-view>` por mensagem + botão "Sair".
    - Sidebar e topbar continuam visíveis com chrome mínimo (tenant name + user menu).

11. **DEFERRED ao final da Fase 9**
    - **Testes E2E Playwright** (T011/T020/T024/T027/T040): especificados no `quickstart.md § Lote E` — requer Sail + browser headless. Cenários documentados; pendentes de implementação concreta.
    - **Audit a11y** (T041): roda manualmente via Chrome DevTools Lighthouse; meta SC-007 = 0 violations sérias/críticas.
    - **Suite full PHP** (T047): rodar `vendor/bin/sail artisan test --compact` para confirmar zero regressão pós router refactor.
    - **Smoke checklist** (T044): validar 6 maiores rotas (Agenda, Pacientes, Inbox, Receituários, Campanhas, Relatórios Executivo) sem regressão visual.
    - **`pacientes.show` route precedence**: ordem das rotas dinâmicas (`:id`) vs estáticas (`/novo`, `/mesclagem`, `/funil`, `/importar`) ajustada para evitar shadow — verificar manualmente.

## Dashboard Home (Fase 10) — Key Patterns

When working on the Dashboard Home (`/panel`) features post-Fase 10, remember:

1. **Endpoint único consolidado em `GET /api/v1/panel/home?scope=user|clinic`**
   - Retorna 4 seções (kpis + upcoming_appointments + attention_items + recent_activity) em UMA response — atende SC-008 (1 request por carga).
   - Cache Redis 30s escopado por `panel_home:{tenant_id}:{user_id}:{scope}` (R2/R4).
   - **NÃO criar endpoints separados por seção** — viola scope-of-1 e fragmenta cache.

2. **4 collectors com degradação graceful em `PanelHomeService::safeRun()`**
   - `KpiCollector`, `UpcomingAppointmentsCollector`, `AttentionItemsCollector`, `RecentActivityCollector`.
   - Falha em 1 collector → `section = null + error=true`, demais seções permanecem normais (R13). Sentry tag `panel_home.section_failed=<section>` + métrica `panel_home_section_failures_total`.
   - **Nunca lançar 500 por falha parcial** — usuário perde valor de todas as outras seções.

3. **`PanelHomePolicy` é o ÚNICO ponto de auth dentro do dashboard**
   - `canSeeClinicScope`: força `scope_applied='user'` se user não tem `admin-clinica` (Q1 da clarification — sem 403, downgrade silencioso).
   - `canSeeWebhookDlqAlerts`: filtra alertas `webhook_dlq` da lista.
   - `canSeeConfirmationAlerts`: filtra alertas `confirmation_pending`.
   - **Defesa em profundidade**: gates dentro do payload (não no middleware) para retornar 200 com lista filtrada.

4. **Q1 — "Minha visão" para admin+medico escopa como profissional**
   - `scope_applied='user'` SEMPRE filtra por `professional_id = Professional.where(user_id=current).pluck('id')` em appointments e pacientes; por `professional_id = current.user_id` em prescriptions (modelo divergente — Prescription.professional_id → User direto).
   - Toggle continua disponível para alternar para "clinic".

5. **Q3 — Alerta `paciente_funil_stale` filtra por `funilColuna.is_terminal=false`**
   - Implementação simplificou Q3: usa o flag `is_terminal` da `FunilColuna` (model) ao invés de hardcoded slugs.
   - Estágios terminais (`agendado`, `concluído`, `perdido`) ficam fora automaticamente.
   - Config `panel.funil_alert_stages` mantida para uso futuro como restrição adicional opcional.

6. **`AttentionItemDto` heterogêneo com `severityRank()` para sort determinístico**
   - 5 tipos: `conversation_escalated`, `prescription_expiring`, `paciente_funil_stale`, `confirmation_pending`, `webhook_dlq`.
   - Severity ranking: `danger=3 > warn=2 > info=1`. Ordenação: severity DESC → occurredAt DESC.
   - Sort no PHP (Collection::sortBy com 2 callbacks) — não em DB porque a coleção é heterogênea.

7. **Humanizer da timeline com allow-list de event types (LGPD)**
   - `App\Support\AuditLog\Humanizer::humanize($event): { description, link }`.
   - Allow-list em `config/panel.recent_activity_allowlist` — 14 event types curados.
   - **Nunca incluir CPF/email/telefone/conteúdo clínico nas descrições** — gate G6 obrigatório.
   - `paciente.viewed` (visualização de prontuário) NÃO entra na allow-list por design.

8. **Frontend: `usePanelHome` é a única source of truth da página**
   - Encapsula: fetch, loading, error, scope (via `usePanelHomeScope`), refresh manual e auto-refresh.
   - Cancela request anterior via `AbortController` quando scope muda mid-flight (evita dados misturados).
   - Reconcilia scope local quando backend faz downgrade (`data.scope_applied !== local scope` → `setScope(applied)`).

9. **`useAutoRefresh` com Page Visibility API + trigger no return-to-focus**
   - `setInterval` rodando só quando `visibilityState='visible'`.
   - Pausa automática em background (SC-009: 0 requests com aba oculta).
   - Retorno ao foco após mais de `intervalMs/2` em background → refresh imediato.

10. **localStorage `panel_home:scope:v1` separado do `app-shell:preferences:v1`**
    - Aninhado por `tenant_slug → user_id`. Princípio II: chave escopada.
    - **NÃO compartilhar chave com app-shell** — schemas independentes evita acoplamento.
    - Default: `'user'`. Fallback se localStorage indisponível: memória volátil.

11. **DEFERRED ao final da Fase 10** (documentado em `specs/010-dashboard-home/DEFERRED.md`)
    - **11 arquivos de teste** (T013–T017, T023–T025, T030–T033, T039–T041) — gates G1–G10 codificados no contract mas Feature/Unit tests não criados nesta sessão; cenários documentados em quickstart.md
    - **Audit a11y Lighthouse/axe** (T065): manual via Chrome DevTools
    - **E2E Playwright** (T062): jornada US-1+US-2+US-3 deferred
    - **Suite full validation** (T068, T069): `vendor/bin/sail artisan test --compact` 1300+ tests

## Dashboard Executivo (Fase 11) — Key Patterns

When working on Executive Dashboard features post-Fase 11, remember:

1. **Backend Fase 8 95% reusado — apenas 1 linha de mudança**
   - `ExecutiveDashboardController::resolvePeriod()` recebeu case `'24h' => $end->copy()->subHours(24)`.
   - Endpoints + Pinia store + ExecutiveDashboardService permanecem intactos. Gate G8 valida que `reportsStore.js` não é modificado pelo spec 011.

2. **`useExecutiveDashboard` é o ÚNICO consumer recomendado do store**
   - Wrapper sobre `reportsStore` que combina state + window persistente + auto-refresh on window change + abort handling implícito (via store).
   - Page consome o composable; componentes consomem props.
   - **NÃO chamar `useReportsStore().loadExecutive()` direto da page** — sempre via composable para garantir window sync.

3. **localStorage `executive_dashboard:window:v1` — chave SEPARADA**
   - 3 chaves de localStorage hoje: `app-shell:preferences:v1` (spec 009), `panel_home:scope:v1` (spec 010), `executive_dashboard:window:v1` (spec 011).
   - Schemas independentes deliberadamente (R11 do spec 010 / R4 desta spec) para evitar acoplamento e versionar separadamente.
   - Validação `sanitize(value)` no composable força default `'7d'` se localStorage trouxer valor fora do enum `{24h, 7d, 30d, 90d}`.

4. **Polaridade invertida explícita em `KpiCardWithSparkline.vue`**
   - Prop `inversePolarity: boolean` (default false).
   - Métricas com polaridade invertida (menos é melhor): `no_show_rate`, `response_time_first_p95`. Aumentar é vermelho; diminuir é verde.
   - Definição no consumer (page) — `inversePolarityMetrics` Set.
   - Comunicação visual sempre acompanhada de ícone (↑/↓) + texto explícito além da cor (FR-039 a11y).

5. **`Sparkline.vue` stub funcional — preparado para futuro**
   - Aceita `points: number[]`; renderiza `null` se vazio.
   - Quando backend implementar `/reports/executive/series?metric=...&window=...`, basta o consumer passar `sparklinePoints` real.
   - **Backend atual NÃO retorna time-series por métrica** — R2 do research; FR-012/FR-017 DEFERRED.

6. **`PeriodFilter.vue` com `role="tablist"` + keyboard nav**
   - Setas Left/Right deslocam entre os 4 tabs em loop circular.
   - Home/End para primeiro/último.
   - `aria-selected` e `tabindex` reativos.

7. **`StaleDataBanner.vue` — visibilidade condicional**
   - Aparece SOMENTE quando `lagSeconds > 7200` AND `window !== '24h'` (FR-008 — janela 24h é live data, banner não se aplica).
   - Timestamp relativo via Luxon (`pt-BR` locale).

8. **`ExportMenu.vue` — PDF ativo, CSV deferred**
   - Item PDF emite `@export-pdf`; page chama `useExecutiveDashboard.exportPdf()` que delega ao store (Blob download).
   - Item CSV sempre `aria-disabled="true"` com label "em breve" — placeholder consciente (FR-028).
   - Spinner via prop `loading` durante export (`exporting` do composable).

9. **Re-uso de patterns de `KpiCardWithTrend.vue` (Fase 8) preservado**
   - `KpiCardWithSparkline.vue` é VARIANT do existente — não substitui.
   - Drill-down futuro pode continuar usando `KpiCardWithTrend` em outros contextos.

10. **DEFERRED ao final da Fase 11** (`specs/011-dashboard-executivo/DEFERRED.md`)
    - **Sparkline real** (FR-012, FR-017): depende de extensão backend retornando time-series por métrica.
    - **CSV export**: backend endpoint não existe; UI mostra placeholder "em breve".
    - **Drill-down detalhado** dentro do dashboard: rota dedicada.
    - **Comparativo arbitrário** entre 2 períodos custom.
    - **Auto-refresh**: intencionalmente desligado (dashboard analítico).
    - **Filtros adicionais** por profissional/tipo: escopo OperationalReport.
    - **E2E Playwright completo** (T018): cenários documentados em quickstart.
    - **A11y audit Lighthouse** (T019): manual via Chrome DevTools.
