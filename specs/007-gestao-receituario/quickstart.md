# Quickstart — Gestão de Receituários (Fase 7)

**Branch**: `007-gestao-receituario` | **Date**: 2026-05-17

Cenários de smoke E2E executáveis em staging contra a API tenant. Cobre os 3 cenários obrigatórios do briefing constitucional + 2 cenários adicionais.

> **Pré-requisitos no staging**:
> - Tenant `clinica-exemplo` provisionado.
> - Usuários: `dr.silva` (médico), `dra.costa` (médico — outra especialidade), `admin@clinica` (Admin Clínica), `atendimento@clinica` (Atendente).
> - Permissão `prescription.*` semeada via `php artisan tenants:seed --tenant=clinica-exemplo`.
> - Paciente `Maria Souza` (id=42) e Profissional `dr.silva` (id=11) já cadastrados.
> - Módulo habilitado: `tenant.settings.modules.prescriptions.enabled=true`.

---

## Cenário 1 — Acesso indevido a receita controlada retorna 403 ⭐ (Gate constitucional)

**O que valida**: ACs 8.1.5, 8.1.6, 8.4.2, 8.4.5 + Princípio I + Princípio II.

### Passos

1. **`dr.silva` cria receita controlada** para Maria Souza:

   ```bash
   curl -X POST https://clinica-exemplo.paciente360.com.br/api/v1/prescriptions \
     -H "Authorization: Bearer paciente360_<token_silva>" \
     -H "X-Tenant-Slug: clinica-exemplo" \
     -H "Content-Type: application/json" \
     -d '{
       "patient_id": 42,
       "type": "controlled",
       "issued_at": "2026-05-17",
       "items": [{
         "medication_name": "Clonazepam",
         "concentration": "2mg",
         "pharmaceutical_form": "comprimido",
         "posology": "1 comprimido à noite",
         "quantity": "30 comprimidos",
         "treatment_duration": "30 dias"
       }],
       "notes": "Paciente com transtorno de ansiedade — uso conforme protocolo."
     }'
   ```

   **Esperado**: `201 Created`, `expires_at=2026-06-16` (issued + 30d fixos), evento `PrescricaoCriada` emitido.

2. **`atendimento@clinica` (Atendente) tenta visualizar a receita**:

   ```bash
   curl -X GET https://clinica-exemplo.paciente360.com.br/api/v1/prescriptions/{id} \
     -H "Authorization: Bearer paciente360_<token_atendente>" \
     -H "X-Tenant-Slug: clinica-exemplo"
   ```

   **Esperado**: `200 OK` com payload `PrescriptionMasked`:
   ```json
   {
     "id": 123,
     "patient_id": 42,
     "type": "controlled",
     "status": "active",
     "issued_at": "2026-05-17",
     "expires_at": "2026-06-16",
     "masked": true,
     "masked_reason": "Receita controlada — acesso restrito ao emissor e Admin Clínica"
   }
   ```
   - `items` e `notes` **não devem aparecer** no payload.
   - Métrica `prescription_controlled_access_denied_total{tenant=clinica-exemplo}` permanece **sem incremento** (acesso à listagem mascarada não é "denied" — é "redacted").

3. **`atendimento@clinica` tenta ver o conteúdo via endpoint AI**:

   ```bash
   curl -X GET https://clinica-exemplo.paciente360.com.br/api/v1/ai/prescriptions/{id}/context \
     -H "Authorization: Bearer paciente360_<token_atendente>" \
     -H "X-Tenant-Slug: clinica-exemplo"
   ```

   **Esperado**: `403 Forbidden` (endpoint restrito a sistema/IA). Métrica `prescription_controlled_access_denied_total` **incrementa**.

4. **`dra.costa` (médico não-emissor) tenta ver detalhes**:

   ```bash
   curl -X GET https://clinica-exemplo.paciente360.com.br/api/v1/prescriptions/{id} \
     -H "Authorization: Bearer paciente360_<token_costa>" \
     -H "X-Tenant-Slug: clinica-exemplo"
   ```

   **Esperado**: `200 OK` com `PrescriptionMasked` (mesmo de Atendente — Q8b).

5. **`admin@clinica` (Admin Clínica) visualiza com conteúdo completo**:

   ```bash
   curl -X GET https://clinica-exemplo.paciente360.com.br/api/v1/prescriptions/{id} \
     -H "Authorization: Bearer paciente360_<token_admin>" \
     -H "X-Tenant-Slug: clinica-exemplo"
   ```

   **Esperado**:
   - `200 OK` com payload `Prescription` completo (`items`, `notes` visíveis).
   - Evento `PrescricaoControladaVisualizada` emitido.
   - audit_log com `action='prescription.view_controlled'` registrado.

6. **Verificar audit_log**:

   ```bash
   php artisan tinker --execute 'App\Models\AuditLog::where("action","prescription.view_controlled")->latest()->first()->toArray();'
   ```

   **Esperado**: row com `actor_user_id`, `auditable_id` (prescription_id), `ip`, `user_agent`, sem snapshot dos campos clínicos (Q8c).

### Critério de aprovação ✅

- Atendente nunca vê posologia/medicamento de controlada.
- Admin Clínica acessa e a visualização é auditada.
- Métrica de segurança `prescription_controlled_access_denied_total` incrementa em tentativa via endpoint restrito.

---

## Cenário 2 — Receita com 8 dias de validade: apenas alertas D-7 e D-1, renovação via IA, cadência interrompida ⭐

**O que valida**: ACs 8.2.1, 8.2.3, 8.2.4, 8.3.1, 8.3.2 + Q4a + Q5.

### Passos

1. **`dr.silva` cria receita `common`** com `duration_days=30`, **mas ajusta `issued_at` para 22 dias atrás** (simulação de cadastro retroativo):

   ```bash
   curl -X POST https://clinica-exemplo.paciente360.com.br/api/v1/prescriptions \
     -H "Authorization: Bearer paciente360_<token_silva>" \
     -H "X-Tenant-Slug: clinica-exemplo" \
     -H "Content-Type: application/json" \
     -d '{
       "patient_id": 42,
       "type": "common",
       "issued_at": "2026-04-25",
       "duration_days": 30,
       "items": [{
         "medication_name": "Losartana",
         "concentration": "50mg",
         "pharmaceutical_form": "comprimido",
         "posology": "1 comprimido pela manhã",
         "treatment_duration": "uso contínuo"
       }]
     }'
   ```

   **Esperado**: `expires_at=2026-05-25` → hoje é `2026-05-17` → **8 dias para vencer**.

2. **Verificar materialização de alertas**:

   ```bash
   curl -X GET "https://clinica-exemplo.paciente360.com.br/api/v1/prescription-alerts?prescription_id={id}" \
     -H "Authorization: Bearer paciente360_<token_silva>" \
     -H "X-Tenant-Slug: clinica-exemplo"
   ```

   **Esperado**: 3 rows
   - `days15` → status `skipped`, `skip_reason='checkpoint_past_at_creation'` (já passou — Q4a).
   - `days7` → status `pending`, `scheduled_for=2026-05-18`.
   - `days1` → status `pending`, `scheduled_for=2026-05-24`.

3. **Avançar relógio para `2026-05-18 06:00 BRT`** (em staging com TimeHelper):

   ```bash
   php artisan time:set "2026-05-18 06:00:00" --tz=America/Sao_Paulo
   php artisan schedule:run
   ```

   **Esperado**:
   - `ProcessPrescriptionAlertsJob` roda.
   - Lock Redis `prescription_alert:{id}:days7:2026-05-18` criado com TTL 25h.
   - Alerta `days7` transita para `dispatched`.
   - Evento `ReceitaProximaDoVencimento` emitido com 7 campos exatos (sem PII clínica).
   - Mensageria Fase 3 recebe HSM `prescription.expiry_warning_7d`.
   - Broadcast Reverb no canal `prescriptions.{tenant_id}` refresca relatório aberto.

4. **Verificar payload do evento via Sentry/log**:

   ```bash
   php artisan pail --filter='ReceitaProximaDoVencimento'
   ```

   **Esperado**: payload contém **exatamente** `{prescriptionId, patientId, professionalId, professionalName, daysUntilExpiry: 7, prescriptionType: 'common', defaultAppointmentTypeId}`. **Não contém** `medication_name`, `posology`, `notes`.

5. **Simular IA agendando renovação** (stub `POST /ai/prescriptions/{id}/context` + endpoint de agendamento da Fase 5):

   ```bash
   # IA stub busca contexto pseudonimizado
   curl -X GET https://clinica-exemplo.paciente360.com.br/api/v1/ai/prescriptions/{id}/context \
     -H "Authorization: Bearer paciente360_<token_ai_system>" \
     -H "X-Tenant-Slug: clinica-exemplo"

   # IA cria consulta na Fase 5 e gera PrescriptionRenewal
   curl -X POST https://clinica-exemplo.paciente360.com.br/api/v1/prescriptions/{id}/renew \
     -H "Authorization: Bearer paciente360_<token_ai_system>" \
     -H "X-Tenant-Slug: clinica-exemplo" \
     -H "Content-Type: application/json" \
     -d '{"initiated_by": "ai", "appointment_id": 999}'
   ```

   **Esperado**:
   - Row em `prescription_renewals` com `initiated_by='ai'`, `appointment_id=999`.
   - Evento `RenovacaoSolicitadaPelaIA` emitido.
   - Listener `EnqueueInboxTaskOnAiRenewal` cria tarefa na Inbox interna (Fase 3) para `dr.silva`: "Renovação agendada pela IA — paciente Maria Souza".

6. **Médico cria a receita renovada** (após consulta de renovação):

   ```bash
   curl -X POST https://clinica-exemplo.paciente360.com.br/api/v1/prescriptions \
     -H "Authorization: Bearer paciente360_<token_silva>" \
     -H "X-Tenant-Slug: clinica-exemplo" \
     -d '{
       "patient_id": 42,
       "type": "common",
       "issued_at": "2026-05-20",
       "duration_days": 90,
       "renewed_from_id": {ORIGINAL_ID},
       "items": [{...mesmos campos...}]
     }'
   ```

   **Esperado**:
   - `renewed_from_id` setado.
   - Row em `prescription_renewals` atualizada: `renewed_prescription_id` populado.
   - Evento `ReceitaRenovada{old, new, renewed_at}` emitido.
   - Listener `CancelAlertScheduleOnRenewal` transita alerta `days1` da receita original para `cancelled`.
   - Receita original transita para `status='superseded'`.

7. **Avançar relógio para `2026-05-24`** (data do alerta D-1 da receita original):

   ```bash
   php artisan time:set "2026-05-24 06:00:00" --tz=America/Sao_Paulo
   php artisan schedule:run
   ```

   **Esperado**: alerta `days1` da receita original **não dispara** (status já é `cancelled`). Métrica `prescription_alerts_dispatched_total{alert_step=days1}` permanece estável.

### Critério de aprovação ✅

- Receita criada com 8 dias gera apenas 2 alertas materializados (D-7 e D-1, com D-15 `skipped`).
- Payload do evento sem PII clínica (validado via reflection no teste automatizado).
- Renovação via IA: tarefa cai na Inbox interna do médico.
- Cadência interrompida: alerta D-1 da receita original não dispara.

---

## Cenário 3 — PDF substituído mantém versão anterior em S3 ⭐

**O que valida**: AC-8.1.4 + Q7b + R-7P-13 (auditabilidade).

### Passos

1. **`dr.silva` cria receita comum** (qualquer tipo serve — usar `common`).

2. **Upload do primeiro PDF**:

   ```bash
   curl -X POST https://clinica-exemplo.paciente360.com.br/api/v1/prescriptions/{id}/pdf \
     -H "Authorization: Bearer paciente360_<token_silva>" \
     -H "X-Tenant-Slug: clinica-exemplo" \
     -F "pdf=@receita_v0.pdf"
   ```

   **Esperado**: `202 Accepted` com `job_id`. Após processamento (~5s):
   - `prescription.pdf_path = 'prescriptions/{tid}/{pid}/v0.pdf'`
   - `prescription.pdf_version = 0`
   - S3 contém arquivo no path.

3. **Verificar download via URL assinada**:

   ```bash
   curl -X GET https://clinica-exemplo.paciente360.com.br/api/v1/prescriptions/{id}/pdf \
     -H "Authorization: Bearer paciente360_<token_silva>" \
     -H "X-Tenant-Slug: clinica-exemplo"
   ```

   **Esperado**:
   ```json
   {
     "url": "https://s3.../prescriptions/{tid}/{pid}/v0.pdf?X-Amz-Signature=...&X-Amz-Expires=900",
     "expires_at": "2026-05-17T10:15:00Z",
     "version": 0
   }
   ```
   - TTL ≤ 15min (R-7P-08).
   - Audit log de emissão da URL registrado.

4. **Substituir PDF** (upload de nova versão):

   ```bash
   curl -X POST https://clinica-exemplo.paciente360.com.br/api/v1/prescriptions/{id}/pdf \
     -H "Authorization: Bearer paciente360_<token_silva>" \
     -H "X-Tenant-Slug: clinica-exemplo" \
     -F "pdf=@receita_v1.pdf"
   ```

   **Esperado**: após processamento:
   - `prescription.pdf_path = 'prescriptions/{tid}/{pid}/v1.pdf'`
   - `prescription.pdf_version = 1`
   - S3 contém **AMBOS** os arquivos: `v0.pdf` e `v1.pdf`.

5. **Verificar versão anterior preservada no S3**:

   ```bash
   php artisan tinker --execute 'Storage::disk("s3")->files("prescriptions/1/{pid}/");'
   ```

   **Esperado**: array `["prescriptions/1/{pid}/v0.pdf", "prescriptions/1/{pid}/v1.pdf"]`.

6. **Auditoria da substituição**:

   ```bash
   php artisan tinker --execute 'App\Models\AuditLog::where("action","prescription.pdf_replaced")->latest()->first();'
   ```

   **Esperado**: row com `actor_user_id`, `auditable_id=prescription_id`, `metadata={previous_version: 0, new_version: 1}`.

### Critério de aprovação ✅

- Após substituição, ambos `v0.pdf` e `v1.pdf` estão no S3.
- URL assinada baixa apenas a versão corrente (`v1`).
- Audit log da substituição registrado.

---

## Cenário 4 — Cross-tenant: tenant A não vê receita de tenant B (404)

**O que valida**: AC-8.4.9 + Princípio II.

### Passos

1. Em `clinica-exemplo`: criar receita controlada (id=123, vide Cenário 1).
2. Em `outra-clinica` (tenant B), com usuário `dr.outro`:

   ```bash
   curl -X GET https://outra-clinica.paciente360.com.br/api/v1/prescriptions/123 \
     -H "Authorization: Bearer paciente360_<token_outro>" \
     -H "X-Tenant-Slug: outra-clinica"
   ```

   **Esperado**: `404 Not Found` (não 403 — não vaza existência).

3. Tentativa via export:

   ```bash
   curl -X GET "https://outra-clinica.paciente360.com.br/api/v1/prescription-reports/export?type=controlled" \
     -H "Authorization: Bearer paciente360_<token_outro>" \
     -H "X-Tenant-Slug: outra-clinica"
   ```

   **Esperado**: CSV vazio (sem rows do tenant A).

4. Verificar audit:

   ```bash
   php artisan tinker --execute 'App\Models\AuditLog::where("action","cross_tenant_attempt")->latest()->first();'
   ```

   **Esperado**: row com `actor_user_id` de `dr.outro` registrada.

### Critério de aprovação ✅

- Tenant B recebe 404 (não 403).
- Audit log de tentativa cross-tenant registrado.
- Export CSV não contém receitas do tenant A.

---

## Cenário 5 — WhatsApp fora da janela 24h sem template aprovado bloqueia alerta

**O que valida**: AC-8.2.5 + Princípio VI + Q4d.

### Passos

1. Configurar paciente Maria Souza para canal preferido = WhatsApp.
2. Garantir que a clínica **não tem** template HSM `prescription.expiry_warning_7d` aprovado pela Meta (estado inicial em staging).
3. Criar receita comum com `expires_at` em 7 dias.
4. Avançar relógio para o checkpoint D-7 (06:00 BRT).
5. Executar `php artisan schedule:run`.

   **Esperado**:
   - Alerta `days7` transita para `status='blocked_no_template'`.
   - Tarefa manual criada na Inbox da clínica (Fase 3) com motivo "Template HSM ausente — alerta de vencimento de receita não enviado a Maria Souza".
   - Métrica `prescription_alerts_blocked_total{reason=no_template,tenant=clinica-exemplo}` incrementa.
6. Aprovar template HSM (simulação via seed).
7. Re-disparar o job (`php artisan prescriptions:process-alerts --retry-blocked`).

   **Esperado**: alerta transita de `blocked_no_template` para `dispatched`.

### Critério de aprovação ✅

- Sem template HSM aprovado, alerta **não é enviado silenciosamente** — cai em Inbox como tarefa manual.
- Após aprovação, retry do job dispara com sucesso.

---

## Performance — Smoke benchmark do relatório (NFR-002)

Para tenants populosos:

```bash
# Seed 50k receitas em clinica-grande
php artisan tinker --execute 'App\Domain\Prescription\Prescription\Prescription::factory()->count(50000)->for(Tenant::find(2))->create();'

# Benchmark da primeira página do relatório
time curl -s -X GET "https://clinica-grande.paciente360.com.br/api/v1/prescription-reports?status=active&window_days=30" \
  -H "Authorization: Bearer paciente360_<token_admin>" \
  -H "X-Tenant-Slug: clinica-grande" > /dev/null
```

**Esperado**: `real ≤ 1.5s` p95 (NFR-002 / SC-007). Repetir 10x e tirar mediana.

---

## Operação — Comandos artisan diários

| Command | Schedule | Função |
|---|---|---|
| `prescriptions:process-alerts` | `dailyAt('06:00')` BRT | Identifica receitas em D-15/7/1, materializa em `prescription_alerts`, dispara `DispatchPrescriptionAlertJob` |
| `prescriptions:expire-active` | `dailyAt('00:30')` BRT | Transita receitas com `expires_at < today` de `active` para implicit `expired` (estado calculado, sem alter no DB) e emite `ReceitaVencida` |
| `prescriptions:purge-old-pdf-versions` | `weeklyOn(1, '02:00')` | Soft-delete versões >5 mais antigas de cada PDF |

---

**FIM DO QUICKSTART** — 5 cenários cobertos. Smoke completo em staging deve passar em ≤ 30min.
