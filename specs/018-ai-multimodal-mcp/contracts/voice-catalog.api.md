# Contract — Voice Catalog (US5)

Catálogo global de vozes (curado pelo super-admin via Filament) + endpoint público para admins de clínica escolherem voz na Persona.

---

## SPA Tenant (admin de clínica)

### 1. `GET /api/v1/ai/voices` — listar vozes ativas

Middleware: `['auth:sanctum', 'tenant.slug', 'tenant.not-suspended']`. Permission: `ai.persona.manage`.

### Query params

- `language` (optional, default `pt-BR`)

### Response 200

```json
{
  "data": [
    {
      "id": 3,
      "display_name": "Camila Acolhedora",
      "gender": "f",
      "tone": "acolhedor",
      "language": "pt-BR",
      "preview_url": "https://app.crm.com.br/storage/voice-previews/v3.mp3",
      "is_system_default": false
    },
    {
      "id": 5,
      "display_name": "Carlos Profissional",
      "gender": "m",
      "tone": "profissional",
      "language": "pt-BR",
      "preview_url": "https://app.crm.com.br/storage/voice-previews/v5.mp3",
      "is_system_default": true
    }
  ]
}
```

**Não expõe** `provider_voice_id` nem `provider` (FR-037c — identificadores técnicos ficam internos).

### 2. `PUT /api/v1/ai/personas/{persona}` — atualiza Persona (campo `voice_id`)

Endpoint já existente da Fase 15; passa a aceitar `voice_id` (FK opcional para `voice_catalog`). Validação: voz precisa estar `is_active=true` e na `language` compatível.

### 3. `GET /api/v1/tenant/settings/voice` — voz default do tenant

Permission: `ai.persona.manage`.

```json
{ "data": { "default_voice_id": 5, "default_voice": { ... } } }
```

### 4. `PUT /api/v1/tenant/settings/voice` — set default

```json
{ "default_voice_id": 3 }
```

---

## Super-Admin (Filament)

### Resource: `App\Filament\Resources\VoiceCatalogResource`

- Listagem com colunas: provider, display_name, gender, tone, language, is_active, is_system_default.
- Form: campos correspondentes; upload do `preview_audio` (gera path em storage public).
- Actions: ativar/desativar; marcar como system default (com guarda — só 1 por language).
- Filter: `language`, `gender`, `tone`, `is_active`.

Sem endpoint REST — Filament resolve no contexto do super admin (Princípio VII v1.4.0).
