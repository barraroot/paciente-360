# Documento de Requisitos – CRM Médico SaaS com Atendimento Omnichannel

## 1. Visão Geral do Projeto

### 1.1 Descrição
Plataforma SaaS multi-tenant voltada para consultórios e clínicas médicas que centraliza o relacionamento com pacientes através de atendimento automatizado via **WhatsApp**, **Instagram Direct** e **chat web** embutido no site do cliente. O sistema utiliza **Inteligência Artificial** (matricial/agêntica) para qualificar leads, agendar consultas e retornos, gerenciar o ciclo de vida de receituários e nutrir o relacionamento com pacientes ativos e inativos.

### 1.2 Objetivos de Negócio
- Reduzir o tempo de resposta a novos leads para próximo de zero (atendimento 24/7).
- Aumentar a taxa de conversão de lead → consulta agendada.
- Diminuir faltas (no-show) através de confirmações e lembretes automáticos.
- Reativar pacientes com receituários próximos do vencimento.
- Centralizar todos os canais de comunicação em uma única caixa unificada.
- Oferecer modelo de assinatura escalável por consultório, profissional ou volume de mensagens.

### 1.3 Público-Alvo
- Consultórios individuais (1 médico + secretária).
- Clínicas de pequeno e médio porte (2 a 20 profissionais).
- Redes de clínicas (multi-unidade).

### 1.4 Stack Técnica
- **Backend:** Laravel 11 (API REST), Sanctum, Spatie Permissions, Filament 5 (admin interno), Laravel Reverb (WebSockets), Horizon (filas).
- **Frontend:** Vue 3 + Composition API + Pinia + Vue Router.
- **Banco:** PostgreSQL/MySQL, Redis (cache/filas).
- **IA:** Camada de orquestração agêntica (matricial) com LLM para NLU, classificação de intenção e geração de respostas.
- **Infra:** Docker Compose, Nginx, CI/CD via GitHub Actions.

---

## 2. Requisitos Funcionais

### 2.1 Multi-tenancy e Gestão SaaS
- **RF-001:** Cadastro e onboarding de novas clínicas (tenants) com isolamento de dados.
- **RF-002:** Planos de assinatura com limites configuráveis (nº de profissionais, nº de mensagens IA/mês, nº de canais conectados, nº de usuários).
- **RF-003:** Gestão de billing: cobrança recorrente, upgrade/downgrade, suspensão por inadimplência, período de trial.
- **RF-004:** Painel administrativo (super admin) para gerenciar tenants, planos, métricas globais e suporte.
- **RF-005:** Domínio/subdomínio personalizado por tenant (ex.: `clinica.crm.com.br`).

### 2.2 Gestão de Usuários e Permissões
- **RF-006:** Perfis: Super Admin, Admin Clínica, Médico, Secretária/Recepcionista, Atendente, Financeiro.
- **RF-007:** Controle granular de permissões por módulo via Spatie.
- **RF-008:** Login com e-mail/senha, 2FA opcional, recuperação de senha, SSO Google (futuro).
- **RF-009:** Log de auditoria de ações sensíveis (acesso a prontuário, alteração de agenda, exclusões).

### 2.3 Cadastro e Gestão de Pacientes (CRM Core)
- **RF-010:** Cadastro completo de paciente: dados pessoais, contato, convênio, histórico, tags, origem do lead.
- **RF-011:** Linha do tempo unificada do paciente (mensagens, consultas, receitas, anotações).
- **RF-012:** Segmentação por tags, status (lead, ativo, inativo, em tratamento), profissional responsável.
- **RF-013:** Importação em massa de pacientes (CSV/Excel).
- **RF-014:** Deduplicação inteligente (mesmo telefone/CPF em canais diferentes).
- **RF-015:** Funil de leads (Kanban): Novo → Qualificado → Agendado → Compareceu → Perdido.

### 2.4 Atendimento Omnichannel
- **RF-016:** Integração com **WhatsApp Business Cloud API** (oficial Meta) para envio/recebimento de mensagens, mídias, templates.
- **RF-017:** Integração com **Instagram Direct** via Graph API.
- **RF-018:** Widget de **chat web** (JavaScript embutível) para o site do cliente, customizável (cores, logo, mensagem inicial).
- **RF-019:** Caixa de entrada unificada com filtros por canal, status, atendente, profissional.
- **RF-020:** Atribuição manual ou automática de conversas a atendentes.
- **RF-021:** Transferência de conversa entre atendentes com nota interna.
- **RF-022:** Respostas rápidas (templates) e atalhos.
- **RF-023:** Envio de mídias (imagens, PDFs de receitas, áudios).
- **RF-024:** Indicador de digitação, status de leitura, presença online em tempo real (Reverb).
- **RF-025:** Modo "humano assume" – pausar IA quando atendente entra na conversa.

### 2.5 Inteligência Artificial Matricial (Agente Automático)
- **RF-026:** Agente IA com base de conhecimento configurável por clínica (FAQ, procedimentos, valores, convênios aceitos, horários).
- **RF-027:** Classificação de intenção: novo agendamento, retorno, dúvida, cancelamento, renovação de receita, reclamação, urgência.
- **RF-028:** Roteamento matricial: cada intenção dispara um fluxo/agente especializado (ex.: agente de agendamento, agente de receituário).
- **RF-029:** Coleta estruturada de dados do paciente em linguagem natural (nome, convênio, queixa, preferência de horário).
- **RF-030:** Escalonamento automático para humano em casos de: urgência médica detectada, baixa confiança da IA, solicitação explícita do paciente.
- **RF-031:** Detecção de sentimento e priorização de conversas insatisfeitas.
- **RF-032:** Treinamento contínuo: atendentes podem corrigir respostas da IA e alimentar a base.
- **RF-033:** Limites de segurança: a IA nunca dá diagnóstico, prescrição ou orientação clínica – apenas direciona.
- **RF-034:** Logs completos de cada decisão da IA (prompt, contexto, resposta, ação tomada) para auditoria.

### 2.6 Agendamento de Consultas
- **RF-035:** Agenda por profissional com horários de trabalho, intervalos, bloqueios e férias.
- **RF-036:** Tipos de atendimento configuráveis (consulta, retorno, exame, telemedicina) com duração e valor.
- **RF-037:** Agendamento via IA no chat: oferece horários disponíveis, confirma e registra.
- **RF-038:** Agendamento manual via painel (drag-and-drop).
- **RF-039:** Confirmação automática 24h e 2h antes via canal de origem.
- **RF-040:** Reagendamento e cancelamento pelo paciente via chat.
- **RF-041:** Lista de espera automática: notifica paciente quando vaga abre.
- **RF-042:** Sincronização bidirecional com Google Calendar e Outlook (por profissional).
- **RF-043:** Bloqueio de horários para emergências/encaixes.

### 2.7 Gestão de Retornos
- **RF-044:** Definição de prazo de retorno por tipo de consulta/profissional.
- **RF-045:** Disparo automático de mensagem de retorno no canal preferido do paciente.
- **RF-046:** Cadência configurável (ex.: 7 dias antes, no dia, 3 dias depois se não agendou).
- **RF-047:** Relatório de pacientes pendentes de retorno.

### 2.8 Gestão de Receituários
- **RF-048:** Cadastro de receituário vinculado ao paciente: medicamentos, posologia, data de emissão, validade.
- **RF-049:** Upload do PDF da receita (anexo).
- **RF-050:** Alerta automático antes do vencimento (15, 7 e 1 dia antes – configurável).
- **RF-051:** Disparo de mensagem proativa via IA oferecendo agendamento para renovação.
- **RF-052:** Diferenciação entre receitas comuns, especiais e controladas (regras distintas).
- **RF-053:** Relatório de receitas vencidas, a vencer e renovadas.

### 2.9 Campanhas e Reativação
- **RF-054:** Disparo em massa segmentado (respeitando opt-in e janela de 24h do WhatsApp).
- **RF-055:** Templates aprovados pela Meta para mensagens fora da janela.
- **RF-056:** Campanhas de reativação de pacientes inativos (configurável: 6m, 1a sem consulta).
- **RF-057:** Campanhas sazonais (vacinação, check-up anual, datas comemorativas).

### 2.10 Relatórios e Dashboard
- **RF-058:** Dashboard executivo: leads por canal, taxa de conversão, no-show, NPS, faturamento estimado.
- **RF-059:** Relatórios operacionais: tempo médio de resposta, volume por atendente, performance da IA.
- **RF-060:** Relatórios clínicos: ocupação por profissional, tipos de procedimento, retornos.
- **RF-061:** Exportação em CSV/PDF.

### 2.11 Integrações
- **RF-062:** Webhooks para eventos (novo lead, agendamento, cancelamento) – integração com sistemas externos.
- **RF-063:** API pública documentada (OpenAPI) para parceiros.
- **RF-064:** Integração com gateways de pagamento para sinal/pré-pagamento de consultas (Stripe, Mercado Pago, Pagar.me).
- **RF-065:** Integração com prontuário eletrônico (futuro – pelo menos preparar contratos de API).

---

## 3. Requisitos Não Funcionais

### 3.1 Desempenho
- **RNF-001:** Tempo de resposta da API < 300ms para 95% das requisições.
- **RNF-002:** Resposta da IA em até 5 segundos para mensagens recebidas.
- **RNF-003:** Suporte a 1000 conversas simultâneas por tenant em horário de pico.

### 3.2 Escalabilidade
- **RNF-004:** Arquitetura horizontalmente escalável (filas via Horizon, workers separados para IA).
- **RNF-005:** Isolamento de tenants para evitar "noisy neighbor".

### 3.3 Segurança e Privacidade
- **RNF-006:** Conformidade com **LGPD**: consentimento, finalidade, direito ao esquecimento, portabilidade.
- **RNF-007:** Criptografia em trânsito (TLS 1.3) e em repouso (dados sensíveis).
- **RNF-008:** Hash de senhas com bcrypt/argon2.
- **RNF-009:** Rate limiting por tenant e por endpoint.
- **RNF-010:** Política de retenção e anonimização de dados.
- **RNF-011:** Termo de uso e política de privacidade aceitos no onboarding.
- **RNF-012:** Pseudonimização de prompts enviados ao LLM (não enviar CPF/dados clínicos sensíveis ao provedor de IA).

### 3.4 Disponibilidade
- **RNF-013:** SLA de 99,5% de uptime.
- **RNF-014:** Backup diário com retenção de 30 dias e disaster recovery testado.
- **RNF-015:** Monitoramento (Sentry, Telescope, métricas Prometheus/Grafana).

### 3.5 Usabilidade
- **RNF-016:** Interface responsiva (desktop e mobile).
- **RNF-017:** Acessibilidade WCAG AA nas telas principais.
- **RNF-018:** Idioma padrão pt-BR, com arquitetura preparada para i18n.

### 3.6 Manutenibilidade
- **RNF-019:** Cobertura de testes mínima de 70% no backend (Pest/PHPUnit).
- **RNF-020:** Testes E2E nas jornadas críticas (Playwright/Cypress).
- **RNF-021:** Documentação técnica e de API mantida (Scribe/Swagger).

---

## 4. Restrições e Premissas

- O atendimento por IA **não substitui orientação médica** – escopo limitado a CRM/agendamento.
- WhatsApp exige conta Business verificada e templates aprovados pela Meta para envio ativo.
- Instagram exige conta profissional vinculada a uma página do Facebook.
- Custos variáveis com LLM e WhatsApp Cloud devem ser repassados ou absorvidos no plano.
- Dados clínicos sensíveis (anamnese, diagnóstico) **ficam fora** desta versão – o produto é CRM, não prontuário.

---

## 5. Módulos do Sistema (Visão Macro)

A arquitetura proposta divide o sistema em módulos funcionais: Autenticação e Tenancy, Pacientes/CRM, Inbox Omnichannel, Motor de IA Matricial, Agenda, Receituários, Campanhas, Relatórios, Billing e Integrações.

---

Pronto, Lucas. Esse é o esqueleto de requisitos. Algumas decisões que sugiro fechar antes de partirmos para as histórias de usuário:

1. **Modelo de cobrança**: por profissional ativo, por volume de mensagens IA, ou híbrido?
2. **Telemedicina**: entra no MVP ou fica para fase 2?
3. **Prontuário eletrônico**: confirma que fica fora do escopo (apenas CRM)?
4. **Multi-unidade**: clínica com várias filiais entra no MVP ou v2?

Quer que eu já prossiga para as **user stories** organizadas por épico/módulo, ou prefere ajustar/adicionar algum requisito antes?
