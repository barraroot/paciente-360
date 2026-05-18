# Research — Gestão de Receituários (Fase 7)

**Branch**: `007-gestao-receituario` | **Date**: 2026-05-17

Phase 0 output do `/speckit-plan`. Resolve decisões técnicas, cita normas regulatórias aplicáveis, registra trade-offs.

> **Princípio editorial**: cada tópico = uma decisão. Cada decisão tem (1) o que foi escolhido, (2) por quê, (3) o que foi rejeitado e por quê. Sem material expositivo — esta é trilha de auditoria de design.

---

## 1. Portaria SVS/MS nº 344/1998 e RDC ANVISA 306/2004 — Aplicabilidade ao CRM

### Decisão
O CRM **registra metadados regulatórios** das receitas (tipo, validade, retenção em farmácia segundo lei) mas **não valida o conteúdo clínico**, **não substitui** o formulário oficial físico/eletrônico de Notificação de Receita, e **não dispensa** o médico das obrigações regulatórias paralelas (impressão em formulário de cor específica, registro em livro próprio, etc.).

### Resumo aplicável

| Norma | Escopo | Aplicação na Fase 7 |
|---|---|---|
| **Portaria SVS/MS 344/1998** (atualizada pela RDC 471/2021) | Controle sanitário de substâncias psicotrópicas, entorpecentes e outras sujeitas a controle especial. Define Listas A1/A2/A3 (entorpecentes + psicotrópicos), B1/B2 (psicotrópicos não-entorpecentes), C (outros), e regimes de receita por lista. | (a) Tipo `controlled` = Listas A → validade **30d fixos**, **1 medicamento por receita**, auditoria de visualização granular. (b) Tipo `special` = Listas B → validade **30d fixos**, alerta de vencimento obrigatório. (c) Tipo `common` = Lista C ou sem controle → validade configurável `{30,60,90,180}` dias. |
| **CFM Resolução nº 1.821/2007** + **CFM 2.299/2021** | Tempo mínimo de guarda de prontuário e documentos clínicos: 20 anos após o último registro do paciente (versão atualizada). | S3 lifecycle desta fase: **5 anos como mínimo inicial** (flag por tenant `prescriptions.retention_years`); **upgrade para 20 anos** condicional à confirmação jurídica antes do go-live (vide R-7P-13 no plan). |
| **CFM Resolução nº 2.314/2022** (Telemedicina) | Receita emitida em consulta de telemedicina **DEVE** ter assinatura digital ICP-Brasil para validade legal. | **Fora do escopo** da Fase 7 — não há assinatura digital. Médico que emite via telemedicina deve usar provedor externo (ex.: Birdid, Memed) e anexar PDF assinado. |
| **RDC ANVISA 306/2004** (atualizada por RDC 222/2018) | Gerenciamento de resíduos de serviços de saúde. | **Não impacta** diretamente o módulo. Listada no briefing por completude — relevância indireta apenas para integração farmácia (não desta fase). |
| **LGPD (Lei 13.709/2018)** | Dados de saúde são sensíveis (Art. 5º, II). Base legal para receitas: prestação de assistência médica (Art. 11, II, f). | Pseudonimização do payload IA (§3 deste research), criptografia em repouso (S3 SSE), URL assinada TTL 15min, audit log de visualização de controlada, mascaramento condicional no relatório. |

### Disclaimer formal a incluir no PDF gerado / interface

> "Esta receita é um registro de metadados no sistema Paciente360. A validade legal da prescrição depende da emissão em formulário oficial conforme Portaria SVS/MS 344/1998 (quando aplicável) e da assinatura do médico. O sistema não substitui o documento físico ou eletrônico assinado digitalmente, nem valida farmacologicamente o conteúdo prescrito."

### Rejeitado

- **Validação farmacológica embarcada** (interação medicamentosa, dose máxima, contraindicação) — rejeitado por escopo (não somos prontuário) e por exigir base de medicamentos certificada com SLA de atualização que ultrapassa o orçamento da fase.
- **Geração de PDF com QR code regulatório ANVISA** (SNGPC) — rejeitado por ausência de spec pública estável; quando ANVISA publicar API, será fase futura.

---

## 2. Versionamento de PDF em S3 — Path com sufixo `_v{n}` vs. S3 native versioning

### Decisão
**Versionamento por path** com sufixo de versão: `prescriptions/{tenant_id}/{prescription_id}/v{pdf_version}.pdf`. A coluna `prescription.pdf_version` controla a versão corrente. Versões antigas permanecem no bucket com paths distintos (`v0.pdf`, `v1.pdf`, ...).

### Por quê

1. **Portabilidade entre disks**: `local` disk usado em testes não suporta S3 versioning nativo — manter mesma estratégia em produção (S3) e teste (local) elimina divergência de comportamento.
2. **Auditoria explícita**: lista de versões é trivial via `Storage::files("prescriptions/{tid}/{pid}/")` — não exige API de versionamento.
3. **Custos previsíveis**: S3 versioning cobra storage em versões deletadas até o expiry — com path explícito, lifecycle policy aplica-se a path inteiro sem regra adicional.
4. **Reversão simples**: para reverter à versão anterior, basta decrementar `pdf_version` no DB e atualizar `pdf_path`. Sem chamada à API S3 de "restore version".

### Política de retenção de versões antigas

- Job `PurgeOldPrescriptionPdfVersionsJob` rodando semanalmente mantém as **últimas 5 versões** por receita. Acima disso, versões mais antigas são **soft-deletadas** com tag S3 `deleted_at` (não removidas fisicamente — recuperáveis por suporte por 90 dias).
- Receitas controladas: **todas as versões preservadas indefinidamente** dentro da janela de retenção total (5/20 anos) — exigência regulatória.

### Rejeitado

- **S3 native versioning** — exige enable do bucket inteiro (afeta outros módulos que reusam o disk da Fase 3); comportamento diferente em disk `local` quebra testes; lifecycle de versões deletadas é cobrada e difícil de auditar.
- **Versão única (sobrescreve)** — viola Princípio III (auditabilidade); briefing C4 exige manter versão anterior.
- **Versionamento por banco (BLOB)** — performance inaceitável para PDF de 10MB; viola separação de concerns (DB ≠ storage de mídia).

---

## 3. Pseudonimização do payload de evento para IA — Pattern reutilizável

### Decisão

Adotar **interface marcadora** `ContainsNoClinicalData` em PHP, aplicada aos eventos que serão consumidos pela IA. A interface não declara métodos — apenas marca o evento como "auditado e validado para sair com PII clínica zero". Teste automatizado por reflection verifica que toda classe que implementa `ContainsNoClinicalData` tem apenas campos da allowlist conhecida.

### Pattern

```text
// app/Support/Lgpd/ContainsNoClinicalData.php
interface ContainsNoClinicalData
{
    // Marker interface — sem métodos.
    // Toda classe que implementa precisa passar PrescriptionEventPayloadLgpdTest
    // (e seus equivalentes em outros domínios).
}

// app/Events/Prescription/ReceitaProximaDoVencimento.php
final class ReceitaProximaDoVencimento implements ContainsNoClinicalData
{
    public function __construct(
        public readonly int $prescriptionId,
        public readonly int $patientId,
        public readonly int $professionalId,
        public readonly string $professionalName,
        public readonly int $daysUntilExpiry,
        public readonly PrescriptionType $prescriptionType,   // enum granular (comum|especial|controlada)
        public readonly ?int $defaultAppointmentTypeId,
    ) {}
}
```

### Teste de gate

```text
// tests/Feature/Prescription/PrescriptionEventPayloadLgpdTest.php
public function test_receita_proxima_do_vencimento_has_no_clinical_data(): void
{
    $reflection = new ReflectionClass(ReceitaProximaDoVencimento::class);
    $properties = collect($reflection->getProperties())
        ->map(fn($p) => $p->getName())
        ->toArray();

    $allowedFields = [
        'prescriptionId', 'patientId', 'professionalId', 'professionalName',
        'daysUntilExpiry', 'prescriptionType', 'defaultAppointmentTypeId',
    ];

    $this->assertEqualsCanonicalizing($allowedFields, $properties,
        'Payload do evento mudou! Revisão LGPD obrigatória antes de adicionar campos.'
    );
}
```

### Extensão para futuros eventos

Toda nova feature que precisar emitir evento consumível pela IA:
1. Implementa `ContainsNoClinicalData`.
2. Lista campos como `readonly public` no constructor (PHP 8).
3. Adiciona teste equivalente (reflection + allowlist).
4. Documenta no docblock o motivo de cada campo.

### Rejeitado

- **Filtro middleware em runtime** (interceptar broadcast e remover campos clínicos) — frágil: nada impede o construtor de receber campos sensíveis; o filtro precisaria conhecer a estrutura. Falha em escala.
- **Tipo `array` com schema JSON externo** — perde tipagem PHP; spec drift entre código e schema.
- **Trait `WithoutClinicalData`** — não é gate verificável; trait pode ser ignorada sem error.

---

## 4. Idempotência de alertas — Redis TTL

### Decisão

Chave Redis `prescription_alert:{prescription_id}:{days_before}:{date_iso}` com TTL **25 horas**. Aplicada **antes** do INSERT em `prescription_alerts` (defesa em profundidade — `UNIQUE (prescription_id, alert_type)` no DB é o gate final).

### Por quê 25h e não 24h

- Cron diário roda às 06:00 BRT. Se o run de hoje atrasar e o de amanhã começar antes do anterior terminar, há sobreposição. TTL 25h cobre essa sobreposição sem desligar idempotência.
- `withoutOverlapping()` do Laravel mitiga, mas não cobre caso de worker travado e retomado por outro nó.

### Referência cruzada

O pattern **já existe no projeto** desde a Fase 5 nos commands `agenda:cleanup-expired-reservations`, `agenda:dispatch-confirmations`, `agenda:auto-close-stale-appointments`. Implementação consolidada em `app/Console/Commands/Agenda*Command.php` — replicada nesta fase em `PrescriptionsProcessAlertsCommand`.

### Pseudocódigo

```text
// app/Domain/Prescription/Alert/PrescriptionAlertIdempotencyKey.php
class PrescriptionAlertIdempotencyKey
{
    public static function for(int $prescriptionId, AlertType $type, CarbonImmutable $date): string
    {
        return sprintf(
            'prescription_alert:%d:%s:%s',
            $prescriptionId,
            $type->value,
            $date->toDateString()
        );
    }
}

// Uso no Job:
$key = PrescriptionAlertIdempotencyKey::for($p->id, AlertType::Days15, today());
if (! Redis::set($key, 1, 'EX', 90_000, 'NX')) {
    // Já processado — descarta
    PrescriptionMetrics::alertIdempotencyHit($p->tenant_id, AlertType::Days15);
    return;
}
// Continua com INSERT + dispatch
```

### Rejeitado

- **DB lock advisory** — funciona mas custa round-trip ao PG por alerta; em 10k receitas/dia/tenant a latência soma.
- **Apenas DB UNIQUE** — perde a janela de "alerta calculado mas não INSERTed ainda" — race entre dois workers concorrentes pode duplicar lógica downstream (broadcast Reverb, métricas) antes do INSERT falhar.

---

## 5. Catálogo ANVISA / TISS de medicamentos — Roadmap, não MVP

### Decisão

**Não implementar** tabela de catálogo de medicamentos nesta fase. `prescription_items.medication_name` permanece texto livre com **autocomplete via histórico do próprio médico** (Q2 do spec). Avaliação completa de catálogo fica como **item de roadmap** para fase futura quando feature de relatório agregado por substância for priorizada.

### Análise de complexidade rejeitada para MVP

| Fonte | Tamanho | Atualização | Licença | Dificuldade |
|---|---|---|---|---|
| **ANVISA — Lista de medicamentos registrados** | ~30k SKUs ativos | Mensal (Bizagi/CSV download manual) | Pública | **Alta** — formato CSV instável, sem chave estável entre versões |
| **TISS — Tabela de OPME e Medicamentos (ANS)** | ~50k itens | Trimestral | Pública (DICOM ANS) | Média — tem catálogo TISS mas focado em convênio, não farmácia |
| **Bulário Eletrônico ANVISA** | ~10k princípios ativos + nomes comerciais | Manual | Pública | Alta — não tem API; scrape exige permissão |
| **DEF / Memed** | ~25k SKUs | Diária | **Comercial** | Baixa (API REST) mas custo recorrente |

### Custo estimado de implementação no MVP

- Importação inicial: 2-3 dias (script ETL + storage).
- Manutenção: dias-pessoa/mês para curar entrada/saída de SKUs.
- Risk: SKU rejeitado pela ANVISA continua no catálogo do tenant → bug de compliance.

### Roadmap proposto

- **Fase 8 (Campanhas)** ou **Fase 9 (Relatórios Clínicos)**: avaliar volume de pedidos "quero relatório por substância" — só então decidir entre (1) construir catálogo próprio, (2) integrar provedor comercial (Memed/DEF), ou (3) heurística por normalização (Levenshtein + cluster de variações ortográficas).

---

## 6. Acesso a receita controlada — Risco de insider threat e mitigação

### Decisão

Modelo de **acesso permitido com auditoria forte** — não bloqueia o acesso legítimo (médico emissor + Admin Clínica) mas **cada visualização gera registro auditável** e a UI deixa clara a natureza regulatória.

### Análise de risco

| Vetor | Probabilidade | Impacto | Mitigação aplicada |
|---|---|---|---|
| Médico emissor abusa do acesso ao próprio paciente para outro fim | Baixa | Médio | Auditoria via `PrescricaoControladaVisualizada` + revisão periódica de logs por Admin Clínica |
| Admin Clínica com `view_controlled` extrai dados em massa | Baixa | Alto | Mascaramento ainda no nível de Resource (não confiar só em ability); audit log de exportação CSV granular; alerta Sentry se Admin exportar >100 receitas controladas em <1h |
| Atendente/Recepcionista tenta acessar via manipulação de URL | Média | Alto | Policy 403 + métrica `prescription_controlled_access_denied_total` + Sentry warning automático |
| Vazamento de PDF por compartilhamento de URL assinada | Média | Alto | TTL 15min; aviso explícito na UI; audit log de emissão da URL com `actor_user_id` |
| Médico de outra especialidade no mesmo tenant vê receita do colega | Alta | Médio | Q8 do spec — mascarado também para esse perfil; conteúdo só visível ao emissor + Admin Clínica |
| Backup ou export de DB cai em mãos erradas | Baixa | **Crítico** | Coluna `notes` encrypted via cast Laravel + S3 SSE — PDF e observações ilegíveis sem chave; PII clínica em `prescription_items` continua em plaintext (medicamento/posologia) — risco aceito porque a chave já protege o agregado em outras camadas |

### Sinais de monitoramento (métrica de segurança)

`prescription_controlled_access_denied_total{tenant_id, perfil}` — Prometheus alert se > 10 em 5min para o mesmo tenant (provável tentativa de scan).

### Rejeitado

- **Aprovação multi-fator para visualizar controlada** (médico precisa de TOTP a cada abertura) — atrito excessivo para fluxo clínico legítimo; sem dados que justifiquem essa fricção; risco de médico esperar quebra-galho (compartilhamento de credencial).
- **Visualização one-time** (cada abertura invalida acessos futuros) — quebra fluxo natural (médico revisita receita várias vezes durante o tratamento).
- **Cripto fim-a-fim com chave do médico** — médico perde acesso se perder dispositivo; viola necessidade de Admin Clínica e auditoria.

---

## 7. Decisões adicionais identificadas durante o design

### 7.1 `expires_at` é `DATE` (não `TIMESTAMPTZ`)

**Por quê**: validade de receita é uma data de calendário no fuso do profissional emissor — não um instante. Tratar como `TIMESTAMPTZ` introduziria ambiguidades em mudanças de DST e cross-tenant (médico no Acre vs. Rio Branco).

**Conversão na borda**: controllers convertem `today()` do fuso do profissional em `DATE` antes de comparar com `expires_at`. Reusa `TimezoneResolverService` da Fase 5.

### 7.2 Cast `encrypted` apenas em `notes`

**Por quê**: medicamento e posologia em `prescription_items` permanecem plaintext. Justificativa: (1) índices `pg_trgm` em medicamento exigem texto pesquisável; (2) ferramentas de BI ad-hoc do Admin Clínica precisam ler para relatório; (3) coluna `notes` carrega comentário clínico do médico (potencial PII narrativa) — esse é o campo de maior risco.

Trade-off aceito: backup do DB precisa de proteção forte (já é requisito da Fase 1 — backup criptografado em S3 distinto).

### 7.3 Broadcast Reverb com canal por tenant — `prescriptions.{tenant_id}`

**Por quê**: relatório (US-8.4) precisa refresh em tempo real quando alerta dispara. Canal privado por tenant + autorização via `channels.php` callback que valida `User::tenant_id == $tenant_id`.

```text
// routes/channels.php
Broadcast::channel('prescriptions.{tenantId}', function (User $user, string $tenantId) {
    return (int) $tenantId === $user->tenant_id;
});
```

Reusa pattern Fase 3 (inbox channel).

### 7.4 Filament Resource para super admin

**Por quê**: support team Paciente360 precisa investigar receitas controladas em tickets sem ssh + tinker. Filament Resource em painel super admin separado (cookie session no domínio `crm.com.br` — Fase 4) permite acesso auditável.

Acesso é **registrado** em `audit_logs` com action `super_admin.prescription.viewed`. Confirmar com CS antes de habilitar para produção (R-7P-05).

---

## 8. Referências cruzadas (CLAUDE.md / specs anteriores)

- **Pattern auto-discovery listeners Laravel 13** — descoberto na Fase 5 (Lote F): `CLAUDE.md > Agendamento (Fase 5) — Key Patterns §5`. **Não registrar listener manualmente em AppServiceProvider** — apenas type-hint do `handle()` (replica em §7 do plan).
- **Pattern modal a11y + toast local + popover inline** — Fase 6 UX: `CLAUDE.md > Agendamento (Fase 5) — Key Patterns §11`. Aplicar em todos os componentes Vue desta fase (cancel, upload, renewal).
- **Pattern `User::guardName='web'` pinned** — Fase 4: `CLAUDE.md > Token Auth (Fase 4) — Key Patterns §2`. `PrescriptionPolicy` herda esse comportamento sem ajuste.
- **Pattern `BelongsToTenant`** — Fase 1: `specs/001-fundacao-multitenant/data-model.md`. Aplicado em 5 modelos novos.
- **Pattern Redis TTL idempotente em commands diários** — Fase 5: `app/Console/Commands/Agenda*Command.php`. Replicado em `PrescriptionsProcessAlertsCommand`.

---

**FIM DO RESEARCH** — 6 tópicos do briefing + 4 decisões adicionais. Sem NEEDS CLARIFICATION técnicos remanescentes.
