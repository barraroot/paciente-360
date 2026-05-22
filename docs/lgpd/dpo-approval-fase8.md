# DPO Approval — Fase 8 (Finalização do MVP)

> **T298 (Fase 8 — Polish)** — Placeholder para registro formal de aprovação DPO/jurídico.

## Status

**PENDENTE** — Aprovação real deve ser obtida ANTES do deploy em produção. Este documento é o template a ser preenchido pelo DPO interno após revisão.

## Itens em análise

### Q20 — Política de retenção

| Entidade | Retenção | Justificativa legal |
|----------|----------|---------------------|
| `audit_logs` | 5 anos | Princípio VII Constitution + Art. 16 LGPD |
| `prescriptions` (controladas) | 5 anos | Portaria 344/98 ANVISA |
| `prescriptions` (comuns) | 30 dias após `expires_at` | LGPD minimização |
| `messages` | 2 anos | Lei nº 12.965/14 (Marco Civil) |
| `consent_records` | até revogação + 5 anos | Princípio da prestação de contas |
| `webhook_dead_letter` | 30 dias | Q16 — sem valor após esgotamento |
| `pseudonymization_audits` | 1 ano | Validação operacional |
| `portability_requests` (arquivos) | 7 dias após geração | TTL URL assinada (Q28) |
| `forgetting_requests` (registros) | 5 anos | Prova de execução (Princípio VII) |
| Tenants `canceled` | 30 dias antes de purge | Janela de reativação |

### Q26 — Mapa de anonimização (Direito ao Esquecimento)

Vide `docs/lgpd/privacy-operations.md` § 2 — tabela completa do que é anonimizado / preservado.

**Pontos críticos para revisão DPO**:

1. **Receitas controladas preservadas** mesmo após esquecimento — conflito aparente Art. 18, VI LGPD × Portaria 344/98. Conclusão jurídica: Portaria prevalece por especialidade + obrigação legal explícita (Art. 16, II LGPD).

2. **Mensagens outbound preservadas** sem anonimização — entendimento que o profissional/clínica tem responsabilidade própria sobre o conteúdo enviado (registro profissional CFM).

3. **`appointments.starts_at` preservado** — registro contábil/financeiro (recibo emitido).

### Q29 — Pseudonimização dual layer para IA

1. **Marker interface** `ContainsNoClinicalData` — design-time guarantee.
2. **Pseudonimização runtime** — `PseudonymizationAuditor` audita semanalmente.

DPO precisa validar que **ambas as camadas combinadas** atendem ao Art. 12 LGPD (pseudonimização como técnica de proteção).

### Avaliação de Impacto à Proteção de Dados (DPIA)

Operações da Fase 8 que potencialmente requerem DPIA:

- **Campanhas de massa**: dispatch de ≥100 destinatários/dia exige base legal clara → resolvido via consentimento `marketing` Q24.
- **Webhooks**: compartilhamento com terceiros → resolvido via consentimento `integracoes` Q17.
- **API Pública**: idem webhooks.
- **Super Admin impersonate**: acesso cross-tenant → resolvido via audit completo + banner persistente Gate 7.

## Aprovações necessárias

- [ ] DPO interno
- [ ] Jurídico (review de cláusulas contratuais de webhooks)
- [ ] CTO (alinhamento técnico-legal)

## Próximos passos pós-aprovação

1. Habilitar deploy em produção da Fase 8.
2. Adicionar nota em política de privacidade pública do produto referenciando este documento.
3. Treinamento de Admin Clínicas dos tenants pilotos (operação de esquecimento/portabilidade).

## Histórico de revisões

| Data | Revisor | Status | Notas |
|------|---------|--------|-------|
| 2026-05-22 | (pendente) | RASCUNHO | Documento criado por T298. |
