# Contract — Consent Transcricao (Q-clarify-2=B)

Adiciona o novo valor `transcricao` ao fluxo de consentimentos existente (Fase 8). NÃO é endpoint novo — é extensão do CRUD de consentimentos do paciente.

---

## 1. `GET /api/v1/pacientes/{paciente}/consents`

Endpoint da Fase 8. Passa a retornar a nova finalidade:

### Response 200 (relevante)

```json
{
  "data": {
    "comunicacao": { "granted": true, "granted_at": "2026-05-15T12:00:00Z", "channel": "whatsapp" },
    "marketing":   { "granted": false, "granted_at": null, "revoked_at": null },
    "integracoes": { "granted": true, "granted_at": "..." },
    "transcricao": {
      "granted": false,
      "granted_at": null,
      "revoked_at": null,
      "description": "Permite armazenamento prolongado de áudios para fins de auditoria interna e melhoria do atendimento. Sem esse consentimento, áudios são apagados no prazo padrão e apenas a transcrição em texto permanece.",
      "default_retention_days": 90,
      "extended_retention_days": 365
    }
  }
}
```

---

## 2. `POST /api/v1/pacientes/{paciente}/consents/transcricao/grant`

Concede consent. Pipeline normal Fase 8.

### Response 200

```json
{
  "data": {
    "finalidade": "transcricao",
    "granted": true,
    "granted_at": "2026-05-30T16:00:00Z",
    "channel": "panel"
  }
}
```

---

## 3. `POST /api/v1/pacientes/{paciente}/consents/transcricao/revoke`

Revoga e dispara **purge retroativo** dos áudios brutos do paciente que estão além do prazo padrão (FR-055c).

### Response 200

```json
{
  "data": {
    "finalidade": "transcricao",
    "granted": false,
    "revoked_at": "2026-05-30T16:05:00Z",
    "purge_job_enqueued": true,
    "audios_to_purge": 12
  }
}
```

Job `PurgePatientExtendedAudioJob` enfileirado na fila `compliance`.

---

## 4. UI — `ConsentTranscricaoToggle.vue`

Componente novo no fluxo de consentimentos do paciente. Estados:

- **Estado vazio** (default — consent nunca concedido): toggle off + texto curto + link "saiba mais" (abre modal com `description` completo).
- **Concedido**: toggle on + data + ação "revogar" com confirmação ("Ao revogar, áudios além de 90 dias serão apagados permanentemente. Continuar?").
- **Revogado**: toggle off + data de revogação + ação "conceder novamente".

Acessibilidade: padrão dos toggles existentes (Headless Switch + label).

---

## 5. Migrations

### `..._add_transcricao_to_consent_finalidade_enum.php`

```php
public function up(): void
{
    DB::statement("ALTER TYPE consent_finalidade ADD VALUE IF NOT EXISTS 'transcricao'");
}

public function down(): void
{
    // PostgreSQL não suporta DROP VALUE — migration NÃO reversível.
    // Documentado no docblock e no checklist.
}
```

Migration **fora de transação** (`public bool $withinTransaction = false;`).

---

## 6. Comportamento do job `PurgeExpiredAudioRawJob` (cron diário)

Pseudo:

```php
foreach (AudioTranscription::where('created_at', '<', now()->subDays(default_retention_days))->cursor() as $t) {
    $patient = $t->message->paciente;
    if ($patient?->hasConsent(ConsentFinalidade::Transcricao)) {
        continue; // mantém até o consent ser revogado OU expirar a janela estendida
    }
    Storage::disk($t->media->disk)->delete($t->media->storage_path);
    $t->media->update(['storage_path' => null, 'purged_at' => now()]);
}
```

---

## 7. Auditoria

`ConsentGrantedEvent` / `ConsentRevokedEvent` (Fase 8) já cobrem; nenhum evento novo. PurgeJob emite `AudioPurgedEvent` para audit log com `(audio_count, patient_id, tenant_id, reason)`.
