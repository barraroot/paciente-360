# Domain Events — Fase 2 (CRM Pacientes)

Documento descrevendo os 13 eventos auditáveis da Fase 2 e sua projeção em `eventos_timeline`. Use este guia para integrar novos listeners em fases futuras.

## Convenção

Cada evento:
- Implementa `App\Events\Contracts\Auditable` com trait `IsAuditable`
- Retorna `auditAction(): string` — chave única de auditoria
- Retorna `auditPayload(): array` — dados estruturados para `audit_logs.payload`
- Retorna `auditableModel()` — Model relacionado

Automatically persisted to `audit_logs` via `PersistAuditLogListener`.
Projected to `eventos_timeline` via `RegistraEventoTimelineListener` when `auditableModel()` is `Paciente | Anotacao | Tag`.

---

## Eventos de Paciente

### 1. PacienteCriado

**Trigger**: POST `/api/v1/pacientes` sucesso  
**Payload**:
```json
{
  "paciente_id": 123,
  "nome": "João Silva",
  "cpf": "***.***.***-12",
  "status": "lead",
  "origem": "importacao"
}
```
**Timeline**: ✓ Projetado (auditableModel = Paciente)

---

### 2. PacienteAtualizado

**Trigger**: PATCH `/api/v1/pacientes/{id}` sucesso  
**Payload**:
```json
{
  "paciente_id": 123,
  "campos_alterados": {
    "email": ["old@example.com", "new@example.com"],
    "telefone_primario": ["31988888888", "31999999999"]
  }
}
```
**Timeline**: ✓ Projetado

---

### 3. PacienteStatusAlterado

**Trigger**: PATCH `/api/v1/pacientes/{id}/status` sucesso  
**Payload**:
```json
{
  "paciente_id": 123,
  "status_anterior": "lead",
  "status_novo": "bloqueado",
  "motivo": "sem_interesse"
}
```
**Timeline**: ✓ Projetado

---

### 4. PacienteMesclado

**Trigger**: POST `/api/v1/pacientes/mesclagens` sucesso  
**Payload**:
```json
{
  "paciente_alvo_id": 123,
  "paciente_origem_ids": [124, 125],
  "resolvidos_automaticamente": 2,
  "resolvidos_manualmente": 0
}
```
**Timeline**: ✓ Projetado em ambos (alvo + origens)

---

### 5. PacienteMesclagemRevertida

**Trigger**: POST `/api/v1/pacientes/mesclagens/{id}/reverter` sucesso  
**Payload**:
```json
{
  "mesclagem_id": 99,
  "paciente_alvo_id": 123,
  "pacientes_restaurados_ids": [124, 125]
}
```
**Timeline**: ✓ Projetado

---

### 6. PacienteAnonimizado

**Trigger**: POST `/api/v1/pacientes/{id}/anonimizar` sucesso  
**Payload**:
```json
{
  "paciente_id": 123,
  "campos_zerados": ["nome", "cpf", "telefone_primario", "email", "endereco"]
}
```
**Timeline**: ✓ Projetado

---

### 7. PacientesImportados

**Trigger**: Job `ProcessImportFileJob` completa  
**Payload**:
```json
{
  "importacao_id": 50,
  "total_processados": 100,
  "sucesso": 95,
  "falhas": 5,
  "tempo_segundos": 12
}
```
**Timeline**: ✗ Não projetado (auditableModel = Importacao, não Paciente)

---

### 8. PacientesExportados

**Trigger**: GET `/api/v1/pacientes/exportar?...` sucesso  
**Payload**:
```json
{
  "filtros": {"status": "lead", "origem": "importacao"},
  "total_exportados": 50,
  "formato": "csv"
}
```
**Timeline**: ✗ Não projetado (sem Paciente específico)

---

## Eventos de Anotação

### 9. AnotacaoCriada

**Trigger**: POST `/api/v1/pacientes/{id}/anotacoes` sucesso  
**Payload**:
```json
{
  "anotacao_id": 500,
  "paciente_id": 123,
  "tipo": "clinica",
  "titulo": "Consulta de rotina",
  "caracteres": 250
}
```
**Timeline**: ✓ Projetado

---

### 10. AnotacaoRetratada

**Trigger**: POST `/api/v1/pacientes/{id}/anotacoes/{aid}/retratacao` sucesso  
**Payload**:
```json
{
  "anotacao_id": 500,
  "anotacao_retratacao_id": 501,
  "paciente_id": 123,
  "motivo": "erro de digitacao"
}
```
**Timeline**: ✓ Projetado

---

## Eventos de Tag

### 11. TagAplicada

**Trigger**: POST `/api/v1/pacientes/{id}/tags` sucesso  
**Payload**:
```json
{
  "tag_id": 30,
  "paciente_id": 123,
  "nome": "Diabético",
  "tipo": "livre"
}
```
**Timeline**: ✓ Projetado

---

### 12. TagRemovida

**Trigger**: DELETE `/api/v1/pacientes/{id}/tags/{tid}` sucesso  
**Payload**:
```json
{
  "tag_id": 30,
  "paciente_id": 123,
  "nome": "Diabético"
}
```
**Timeline**: ✓ Projetado

---

## Eventos de Funil

### 13. LeadMovidoNoFunil

**Trigger**: PATCH `/api/v1/pacientes/{id}/funil` sucesso  
**Payload**:
```json
{
  "paciente_id": 123,
  "coluna_anterior_id": 1,
  "coluna_nova_id": 2,
  "coluna_anterior_slug": "novo",
  "coluna_nova_slug": "qualificado",
  "motivo_saida": null,
  "motivo_texto": null
}
```
**Timeline**: ✓ Projetado

---

## Integração em Fases Futuras

Para adicionar novos listeners que reagem a estes eventos:

```php
// app/Listeners/YourListener.php
use App\Events\Paciente\PacienteCriado;

class YourListener
{
    public function handle(PacienteCriado $event): void
    {
        // $event->professional
        // $event->orphanPacienteIds
        // ...
    }
}
```

Registre em `EventServiceProvider.php`:

```php
Event::listen(PacienteCriado::class, YourListener::class);
```

---

## Audit Log Access

Todos os eventos estão em `audit_logs` com estrutura:

```sql
SELECT 
    action, 
    auditable_type, 
    auditable_id, 
    payload,
    created_at
FROM audit_logs
WHERE action LIKE 'paciente.%'
  AND tenant_id = ?
ORDER BY created_at DESC;
```

---

## Timeline Access

Eventos de paciente projetados em `eventos_timeline`:

```sql
SELECT 
    tipo_evento, 
    payload,
    created_at
FROM eventos_timeline
WHERE paciente_id = ?
  AND tenant_id = ?
ORDER BY created_at DESC;
```
