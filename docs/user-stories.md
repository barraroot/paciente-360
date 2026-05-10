# User Stories – CRM Médico SaaS com Atendimento Omnichannel

## Visão Geral

Este documento contém as user stories do **CRM Médico SaaS**, plataforma multi-tenant para consultórios e clínicas que centraliza o relacionamento com pacientes via WhatsApp, Instagram Direct e chat web, com IA matricial para qualificação de leads, agendamento, retornos e renovação de receituários.

**Tipos de usuário:**
- **Visitante** – Usuário não autenticado (paciente em potencial conversando via canal).
- **Paciente** – Pessoa atendida pela clínica (lead, ativo ou inativo); interage majoritariamente via canal externo (WhatsApp, Instagram, chat web).
- **Atendente / Recepcionista** – Operador da caixa de entrada; assume conversas, agenda manualmente, transfere.
- **Médico (Profissional)** – Dono da agenda, emissor de receituários, define prazos de retorno.
- **Financeiro** – Acompanha cobranças, relatórios financeiros e billing do tenant.
- **Admin Clínica** – Gestor do tenant; configura usuários, planos, IA, canais, agenda.
- **Super Admin (Plataforma)** – Operador da plataforma SaaS; gerencia tenants, planos globais e suporte.

**Escopo do MVP (decisões fechadas):**
- Cobrança híbrida: plano base por profissional ativo + cota mensal de mensagens IA com cobrança por excedente.
- Telemedicina nativa fica para a fase 2.
- Multi-unidade (filiais por tenant) fica para v2.
- Pré-pagamento de consultas pelos pacientes fica para fase 2; MVP cobra apenas a assinatura SaaS do tenant (Stripe).
- Prontuário eletrônico fora de escopo.

---

## 1. Multi-tenancy, Onboarding e Billing SaaS

### US-1.1: Cadastro de Nova Clínica (Tenant)
**Como** Visitante interessado em contratar a plataforma
**Quero** me cadastrar e criar a conta da minha clínica
**Para que** eu possa começar a usar o sistema em período de trial

**Critérios de Aceitação:**
- [ ] Formulário coleta: nome da clínica, CNPJ, nome do responsável, e-mail, telefone, senha.
- [ ] E-mail e CNPJ devem ser únicos.
- [ ] Aceite obrigatório dos Termos de Uso e Política de Privacidade (LGPD).
- [ ] Tenant criado com isolamento de dados (banco/schema dedicado conforme estratégia).
- [ ] Subdomínio gerado automaticamente (`<slug>.crm.com.br`) com opção de customizar depois.
- [ ] Trial de 14 dias ativado automaticamente, sem cartão de crédito.
- [ ] E-mail de boas-vindas enviado com checklist de onboarding.
- [ ] Usuário criador recebe perfil **Admin Clínica**.

**Resultado Esperado:** Clínica cadastrada e pronta para iniciar onboarding.

---

### US-1.2: Onboarding Guiado da Clínica
**Como** Admin Clínica recém-cadastrado
**Quero** seguir um onboarding passo a passo
**Para que** o sistema esteja minimamente configurado para uso

**Critérios de Aceitação:**
- [ ] Wizard com etapas: dados da clínica → cadastro do primeiro profissional → conexão de pelo menos um canal → configuração da agenda → base de conhecimento da IA.
- [ ] Progresso salvo entre sessões.
- [ ] Skip opcional em etapas não bloqueantes.
- [ ] Indicador percentual de conclusão.
- [ ] Conclusão habilita o uso pleno da inbox.

**Resultado Esperado:** Clínica configurada para receber o primeiro atendimento.

---

### US-1.3: Assinatura de Plano (Modelo Híbrido)
**Como** Admin Clínica com trial ativo
**Quero** assinar um plano pago
**Para que** eu continue usando após o período de trial

**Critérios de Aceitação:**
- [ ] Página de planos exibe: preço base por profissional ativo + cota mensal de mensagens IA inclusas + valor por mensagem excedente.
- [ ] Admin escolhe quantidade inicial de profissionais.
- [ ] Checkout integrado com Stripe (cartão de crédito).
- [ ] Cobrança recorrente mensal.
- [ ] Confirmação de assinatura por e-mail.
- [ ] Em caso de falha de pagamento, sistema entra em estado **inadimplente** após 3 tentativas; bloqueia funcionalidades não essenciais após 7 dias.

**Resultado Esperado:** Tenant com assinatura ativa e billing automatizado.

---

### US-1.4: Upgrade/Downgrade de Plano
**Como** Admin Clínica
**Quero** alterar o plano ou o número de profissionais
**Para que** o custo acompanhe o uso real da clínica

**Critérios de Aceitação:**
- [ ] Admin pode adicionar/remover profissionais no painel de billing.
- [ ] Cobrança proporcional (proration) calculada automaticamente.
- [ ] Downgrade não bloqueia recursos de imediato — vigência ao fim do ciclo.
- [ ] Histórico de alterações de plano registrado.
- [ ] Notificação por e-mail confirmando alteração.

**Resultado Esperado:** Plano ajustado refletindo uso atual.

---

### US-1.5: Monitoramento de Cota de Mensagens IA
**Como** Admin Clínica
**Quero** acompanhar o consumo de mensagens IA do mês
**Para que** eu possa prever excedentes e ajustar o plano

**Critérios de Aceitação:**
- [ ] Dashboard exibe: cota inclusa, consumido até agora, projeção para fim do mês, custo estimado de excedente.
- [ ] Alerta automático em 80% e 100% da cota.
- [ ] Possibilidade de definir teto de gasto (hard cap) que pausa IA quando atingido.
- [ ] Histórico mensal de consumo.

**Resultado Esperado:** Admin tem visibilidade e controle de custos variáveis de IA.

---

## 2. Autenticação, Usuários e Permissões

### US-2.1: Login de Usuário Interno
**Como** Usuário interno do tenant (Admin, Médico, Atendente, Financeiro)
**Quero** autenticar com e-mail e senha
**Para que** eu acesse o painel da clínica

**Critérios de Aceitação:**
- [ ] Login com e-mail + senha.
- [ ] Suporte a 2FA opcional (TOTP) habilitável por usuário.
- [ ] Sessão via Sanctum com tempo configurável.
- [ ] Bloqueio temporário após 5 tentativas falhas.
- [ ] Redirecionamento ao dashboard apropriado conforme perfil.

**Resultado Esperado:** Usuário autenticado no tenant correto.

---

### US-2.2: Cadastro de Usuários Internos
**Como** Admin Clínica
**Quero** cadastrar atendentes, médicos e financeiro
**Para que** a equipe acesse o sistema com permissões adequadas

**Critérios de Aceitação:**
- [ ] Admin define: nome, e-mail, perfil (Médico/Atendente/Recepcionista/Financeiro), profissionais aos quais pertence (no caso de atendente vinculado).
- [ ] Convite por e-mail com link de definição de senha (válido por 24h).
- [ ] Usuário inativo até aceitar convite.
- [ ] Limite de usuários respeitado conforme plano.
- [ ] Permissões aplicadas via Spatie por módulo (inbox, agenda, pacientes, receituários, relatórios).

**Resultado Esperado:** Equipe cadastrada e pronta para operar.

---

### US-2.3: Recuperação de Senha
**Como** Usuário interno
**Quero** redefinir minha senha
**Para que** eu recupere acesso quando esquecer

**Critérios de Aceitação:**
- [ ] Link "Esqueci minha senha" disponível.
- [ ] E-mail enviado com token válido por 60 minutos.
- [ ] Token de uso único.
- [ ] Política de senha forte (mínimo 8 caracteres, maiúscula, número).
- [ ] Notificação por e-mail após troca confirmada.

**Resultado Esperado:** Usuário recupera acesso de forma segura.

---

### US-2.4: Log de Auditoria
**Como** Admin Clínica
**Quero** consultar log de ações sensíveis
**Para que** eu rastreie alterações e atenda exigências de LGPD

**Critérios de Aceitação:**
- [ ] Log registra: acesso a dados de paciente, alteração de agenda, exclusão de registros, alteração de permissões.
- [ ] Filtros por usuário, data, tipo de ação.
- [ ] Exportação CSV para auditoria externa.
- [ ] Retenção mínima de 1 ano.

**Resultado Esperado:** Histórico completo de ações sensíveis disponível.

---

## 3. CRM – Cadastro e Gestão de Pacientes

### US-3.1: Cadastro Manual de Paciente
**Como** Atendente ou Médico
**Quero** cadastrar um paciente manualmente
**Para que** eu registre alguém que chegou por canal não automatizado

**Critérios de Aceitação:**
- [ ] Formulário coleta: nome, CPF, data de nascimento, telefone, e-mail, convênio, número de carteirinha, tags, origem do lead, profissional responsável.
- [ ] Validação de CPF.
- [ ] Status inicial configurável (lead, ativo).
- [ ] Deduplicação automática: se telefone/CPF já existir, sistema oferece mesclar.
- [ ] Histórico de criação registrado.

**Resultado Esperado:** Paciente cadastrado e pronto para receber agendamento.

---

### US-3.2: Linha do Tempo Unificada do Paciente
**Como** Médico ou Atendente
**Quero** ver todo o histórico de interações de um paciente em uma única visão
**Para que** eu tenha contexto antes de atender

**Critérios de Aceitação:**
- [ ] Timeline cronológica reversa exibe: mensagens (todos os canais), consultas realizadas/canceladas, receituários, anotações internas, mudanças de status.
- [ ] Filtros por tipo de evento e por canal.
- [ ] Anotações internas marcadas como privadas (não visíveis ao paciente).
- [ ] Consulta carrega em < 1s para até 1000 eventos.

**Resultado Esperado:** Visão 360º do paciente acessível em uma tela.

---

### US-3.3: Importação em Massa de Pacientes
**Como** Admin Clínica em onboarding
**Quero** importar a base atual de pacientes via planilha
**Para que** eu não precise recadastrar manualmente

**Critérios de Aceitação:**
- [ ] Upload de arquivo CSV/Excel.
- [ ] Template baixável com colunas obrigatórias e opcionais.
- [ ] Validação prévia: linhas inválidas reportadas com motivo.
- [ ] Deduplicação por CPF/telefone aplicada.
- [ ] Processamento assíncrono via Horizon com barra de progresso.
- [ ] Relatório final: importados, ignorados, com erro.

**Resultado Esperado:** Base de pacientes carregada sem retrabalho manual.

---

### US-3.4: Funil de Leads (Kanban)
**Como** Admin Clínica ou Atendente
**Quero** visualizar e mover leads em um funil Kanban
**Para que** eu acompanhe a conversão até a consulta

**Critérios de Aceitação:**
- [ ] Colunas padrão: Novo → Qualificado → Agendado → Compareceu → Perdido.
- [ ] Drag-and-drop entre colunas.
- [ ] Movimentação automática quando IA qualifica/agenda.
- [ ] Filtros por canal de origem, profissional, data.
- [ ] Card mostra: nome, canal, última interação, valor estimado.

**Resultado Esperado:** Visão clara do pipeline comercial.

---

### US-3.5: Segmentação por Tags e Status
**Como** Admin Clínica
**Quero** aplicar tags e segmentar pacientes
**Para que** eu use os segmentos em campanhas e relatórios

**Critérios de Aceitação:**
- [ ] Cadastro livre de tags (ex.: "diabético", "VIP", "indicação").
- [ ] Status: lead, ativo, em tratamento, inativo, bloqueado.
- [ ] Multi-tag por paciente.
- [ ] Busca/filtro por tag e status.
- [ ] Mudanças de status registradas no log.

**Resultado Esperado:** Pacientes organizados para ações segmentadas.

---

## 4. Atendimento Omnichannel (Inbox)

### US-4.1: Conectar WhatsApp Business
**Como** Admin Clínica
**Quero** conectar a conta oficial do WhatsApp Business
**Para que** mensagens de pacientes cheguem na inbox

**Critérios de Aceitação:**
- [ ] Fluxo OAuth/embed da Meta Cloud API.
- [ ] Validação de número e conta Business verificada.
- [ ] Cadastro de templates aprovados pela Meta.
- [ ] Status da conexão visível (ativo, inválido, expirado).
- [ ] Reenvio de webhook de validação em caso de falha.

**Resultado Esperado:** Canal WhatsApp operando bidirecionalmente.

---

### US-4.2: Conectar Instagram Direct
**Como** Admin Clínica
**Quero** conectar Instagram Direct via Graph API
**Para que** DMs cheguem à inbox unificada

**Critérios de Aceitação:**
- [ ] Login via Facebook OAuth.
- [ ] Validação de conta profissional vinculada a página.
- [ ] Mensagens recebidas em tempo real via webhook.
- [ ] Limitações da janela de 24h documentadas no painel.

**Resultado Esperado:** Instagram operando na inbox.

---

### US-4.3: Widget de Chat Web Embutível
**Como** Admin Clínica
**Quero** gerar um snippet JS para o site da clínica
**Para que** visitantes do site iniciem conversa

**Critérios de Aceitação:**
- [ ] Snippet personalizável: cores, logo, mensagem inicial, horário de funcionamento.
- [ ] Visitante anônimo pode iniciar conversa sem login.
- [ ] Coleta de nome/telefone configurável antes de abrir chat.
- [ ] Mensagens entram na inbox unificada com canal "Web".
- [ ] Indicador online/offline conforme horário configurado.

**Resultado Esperado:** Site da clínica com chat funcional integrado.

---

### US-4.4: Caixa de Entrada Unificada
**Como** Atendente
**Quero** ver todas as conversas de todos os canais em uma única tela
**Para que** eu não troque de ferramenta

**Critérios de Aceitação:**
- [ ] Lista de conversas com: avatar, canal, último trecho, hora, contador de não lidas.
- [ ] Filtros: canal, status (aberto/pendente/resolvido), atendente, profissional, tag.
- [ ] Busca por nome, telefone, conteúdo.
- [ ] Atualização em tempo real via Reverb (WebSocket).
- [ ] Indicador de digitação e status de leitura.

**Resultado Esperado:** Atendente opera todos os canais em uma única interface.

---

### US-4.5: Atribuir e Transferir Conversa
**Como** Atendente ou Admin Clínica
**Quero** atribuir/transferir conversa a outro atendente
**Para que** o caso vá para a pessoa certa

**Critérios de Aceitação:**
- [ ] Atribuição manual por seleção de atendente.
- [ ] Atribuição automática conforme regra (round-robin, profissional vinculado, primeiro disponível).
- [ ] Transferência com nota interna obrigatória.
- [ ] Notificação ao novo responsável.
- [ ] Histórico de atribuições visível na conversa.

**Resultado Esperado:** Conversa roteada corretamente sem perda de contexto.

---

### US-4.6: Modo "Humano Assume"
**Como** Atendente
**Quero** pausar a IA quando entro em uma conversa
**Para que** o paciente não receba mensagens automáticas concorrentes

**Critérios de Aceitação:**
- [ ] Botão "Assumir conversa" disponível.
- [ ] IA pausada automaticamente quando atendente envia mensagem.
- [ ] Indicador visual de "IA pausada" para o atendente.
- [ ] Retomada manual ou automática após X minutos sem interação humana (configurável).

**Resultado Esperado:** Transição IA → humano sem fricção para o paciente.

---

### US-4.7: Respostas Rápidas (Templates)
**Como** Atendente
**Quero** usar templates pré-cadastrados
**Para que** eu responda mais rapidamente

**Critérios de Aceitação:**
- [ ] CRUD de respostas rápidas com atalho (`/preço`, `/horario`).
- [ ] Variáveis dinâmicas (`{nome_paciente}`, `{nome_profissional}`).
- [ ] Atalhos sugeridos por contexto (ex.: ao digitar "/").
- [ ] Compartilhadas entre toda a equipe ou privadas por usuário.

**Resultado Esperado:** Atendimento mais rápido e consistente.

---

## 5. Inteligência Artificial Matricial

### US-5.1: Configurar Base de Conhecimento da IA
**Como** Admin Clínica
**Quero** alimentar a IA com FAQ, procedimentos, valores e convênios
**Para que** as respostas reflitam a realidade da clínica

**Critérios de Aceitação:**
- [ ] Editor para adicionar entradas de conhecimento (pergunta/resposta, blocos de texto).
- [ ] Categorias: valores, convênios, horários, procedimentos, política de cancelamento.
- [ ] Pré-visualização de como a IA responderá usando aquele conhecimento.
- [ ] Versionamento básico (rollback para versão anterior).
- [ ] Pseudonimização: dados pessoais não são enviados ao LLM.

**Resultado Esperado:** IA personalizada para o domínio da clínica.

---

### US-5.2: Classificação Automática de Intenção
**Como** Plataforma (sistema)
**Quero** classificar a intenção de cada mensagem recebida
**Para que** o agente especializado correto seja acionado

**Critérios de Aceitação:**
- [ ] Intenções suportadas no MVP: novo agendamento, retorno, dúvida, cancelamento, renovação de receita, reclamação, urgência.
- [ ] Score de confiança por intenção.
- [ ] Quando score < limiar, escalonar para humano.
- [ ] Logs de classificação consultáveis.

**Resultado Esperado:** Cada mensagem roteada para o fluxo correto automaticamente.

---

### US-5.3: Agendamento via IA no Chat
**Como** Paciente conversando via canal
**Quero** agendar uma consulta sem intervenção humana
**Para que** eu resolva 24/7 sem esperar

**Critérios de Aceitação:**
- [ ] IA coleta: motivo, profissional desejado (ou recomenda), convênio, preferência de horário.
- [ ] Consulta a agenda real e oferece 3 horários disponíveis.
- [ ] Confirmação explícita antes de gravar.
- [ ] Agendamento criado com origem = IA.
- [ ] Resumo enviado ao paciente com data, hora, profissional, endereço.
- [ ] Falha de coleta após 3 tentativas escala para humano.

**Resultado Esperado:** Paciente agendado sem intervenção humana.

---

### US-5.4: Escalonamento Automático para Humano
**Como** Plataforma
**Quero** escalar conversas críticas para um atendente
**Para que** casos sensíveis tenham atenção humana

**Critérios de Aceitação:**
- [ ] Gatilhos: detecção de urgência médica (palavras-chave + contexto), baixa confiança da IA, sentimento muito negativo, pedido explícito do paciente.
- [ ] Conversa marcada como prioridade alta na inbox.
- [ ] Notificação push/sonora ao atendente disponível.
- [ ] IA emite mensagem de transição informando o paciente.

**Resultado Esperado:** Casos críticos não ficam presos com a IA.

---

### US-5.5: Limites de Segurança Clínica
**Como** Plataforma
**Quero** garantir que a IA não dê diagnóstico, prescrição ou orientação clínica
**Para que** a clínica fique em conformidade ética e legal

**Critérios de Aceitação:**
- [ ] Guardrails no prompt do sistema bloqueiam orientação clínica.
- [ ] Resposta padrão para tentativas de obter diagnóstico: redireciona ao agendamento.
- [ ] Auditoria de violações: respostas suspeitas marcadas para revisão.
- [ ] Testes automatizados cobrem cenários de tentativa de bypass.

**Resultado Esperado:** IA opera dentro do escopo CRM, nunca clínico.

---

### US-5.6: Treinamento Contínuo pela Equipe
**Como** Atendente
**Quero** corrigir uma resposta da IA e alimentar a base
**Para que** a IA aprenda com erros e fique mais precisa

**Critérios de Aceitação:**
- [ ] Botão "Corrigir IA" em cada mensagem automática.
- [ ] Atendente fornece resposta correta + categoriza o erro.
- [ ] Correção entra na base de conhecimento (com aprovação do Admin).
- [ ] Painel de IA mostra correções pendentes.

**Resultado Esperado:** Curva de aprendizado da IA acelerada por feedback humano.

---

### US-5.7: Auditoria de Decisões da IA
**Como** Admin Clínica
**Quero** auditar cada decisão tomada pela IA
**Para que** eu entenda por que ela respondeu/agiu daquela forma

**Critérios de Aceitação:**
- [ ] Log armazena: prompt, contexto, intenção classificada, score, resposta gerada, ação executada (ex.: agendar consulta).
- [ ] Filtros por conversa, intenção, data.
- [ ] Exportação CSV.
- [ ] Retenção mínima de 6 meses.

**Resultado Esperado:** Transparência total sobre o comportamento da IA.

---

## 6. Agendamento de Consultas

### US-6.1: Configurar Agenda do Profissional
**Como** Médico ou Admin Clínica
**Quero** definir horários de trabalho, intervalos e bloqueios
**Para que** apenas horários válidos sejam ofertados

**Critérios de Aceitação:**
- [ ] Configuração por dia da semana (início, fim, intervalos).
- [ ] Bloqueios pontuais (férias, feriados, eventos).
- [ ] Tipos de atendimento aceitos por profissional.
- [ ] Buffer entre consultas configurável.
- [ ] Visualização semanal/mensal.

**Resultado Esperado:** Agenda do profissional refletindo realidade.

---

### US-6.2: Tipos de Atendimento Configuráveis
**Como** Admin Clínica
**Quero** cadastrar tipos de atendimento (consulta, retorno, exame)
**Para que** cada um tenha duração, valor e regras próprias

**Critérios de Aceitação:**
- [ ] Cadastro: nome, duração, valor particular, valor convênio, cor no calendário.
- [ ] Definição de quais profissionais executam.
- [ ] Vinculação a intenções de IA (ex.: "renovação de receita" → consulta de retorno).
- [ ] Telemedicina marcada como **fora do MVP** (placeholder).

**Resultado Esperado:** Tipos de atendimento padronizados na clínica.

---

### US-6.3: Agendamento Manual via Painel
**Como** Atendente ou Recepcionista
**Quero** marcar consultas via drag-and-drop no calendário
**Para que** eu agende rapidamente quando o paciente liga

**Critérios de Aceitação:**
- [ ] Visualização de agenda diária/semanal por profissional.
- [ ] Drag-and-drop para criar/mover consulta.
- [ ] Busca de paciente existente ou cadastro rápido.
- [ ] Detecção de conflito de horário.
- [ ] Confirmação enviada ao paciente no canal preferido.

**Resultado Esperado:** Agendamento manual ágil sem fricção.

---

### US-6.4: Confirmação Automática de Consulta
**Como** Plataforma
**Quero** enviar confirmação 24h e 2h antes da consulta
**Para que** a taxa de no-show diminua

**Critérios de Aceitação:**
- [ ] Mensagens enviadas automaticamente nos prazos configurados.
- [ ] Canal = canal de origem do paciente.
- [ ] Paciente responde com "1" (confirma), "2" (remarca), "3" (cancela).
- [ ] Agendamento atualizado conforme resposta.
- [ ] Não-resposta marcada para tentativa de contato manual.

**Resultado Esperado:** Confirmações automatizadas reduzem faltas.

---

### US-6.5: Reagendamento e Cancelamento via Chat
**Como** Paciente
**Quero** remarcar ou cancelar via chat
**Para que** eu não precise ligar para a clínica

**Critérios de Aceitação:**
- [ ] IA reconhece intenção de reagendar/cancelar.
- [ ] Oferece novos horários disponíveis ao reagendar.
- [ ] Aplica política de cancelamento configurada (prazo mínimo).
- [ ] Notifica equipe da clínica.
- [ ] Atualiza agenda em tempo real.

**Resultado Esperado:** Paciente resolve mudanças sozinho com agilidade.

---

### US-6.6: Lista de Espera Automática
**Como** Atendente
**Quero** que pacientes entrem em lista de espera quando não há vaga
**Para que** vagas sejam preenchidas quando outras consultas são canceladas

**Critérios de Aceitação:**
- [ ] Paciente opta por entrar na lista após escolher profissional.
- [ ] Quando vaga abre (cancelamento), sistema notifica primeiros N pacientes elegíveis.
- [ ] Primeiro a confirmar fica com a vaga.
- [ ] Notificação no canal de origem.
- [ ] Relatório de vagas preenchidas por essa via.

**Resultado Esperado:** Otimização da ocupação da agenda.

---

### US-6.7: Sincronização com Google Calendar / Outlook
**Como** Médico
**Quero** que minha agenda sincronize bidirecionalmente com Google/Outlook
**Para que** eu veja consultas em meu calendário pessoal

**Critérios de Aceitação:**
- [ ] OAuth com Google/Microsoft por profissional.
- [ ] Sincronização bidirecional: criação, alteração, cancelamento.
- [ ] Eventos externos bloqueiam horários no CRM.
- [ ] Conflitos resolvidos por regra (CRM como fonte da verdade).

**Resultado Esperado:** Profissional sem retrabalho de agenda dupla.

---

## 7. Gestão de Retornos

### US-7.1: Definir Prazo de Retorno
**Como** Médico ou Admin Clínica
**Quero** definir prazo padrão de retorno por tipo de consulta
**Para que** o sistema gere lembretes automáticos

**Critérios de Aceitação:**
- [ ] Configuração por tipo de consulta e profissional.
- [ ] Permite override por paciente.
- [ ] Cadência configurável: X dias antes, no dia, X dias depois se não agendou.
- [ ] Templates de mensagem editáveis por etapa da cadência.

**Resultado Esperado:** Política de retorno parametrizada.

---

### US-7.2: Disparo Automático de Mensagem de Retorno
**Como** Plataforma
**Quero** enviar mensagem de retorno conforme cadência
**Para que** pacientes voltem sem esforço manual

**Critérios de Aceitação:**
- [ ] Job agendado roda diariamente identificando pacientes elegíveis.
- [ ] Envio respeitando canal preferido e janela do WhatsApp.
- [ ] IA assume conversa caso paciente responda.
- [ ] Cadência interrompe quando paciente agenda.

**Resultado Esperado:** Retornos automatizados com mínima fricção operacional.

---

### US-7.3: Relatório de Retornos Pendentes
**Como** Admin Clínica
**Quero** ver pacientes pendentes de retorno
**Para que** eu acione manualmente quando a automação não converte

**Critérios de Aceitação:**
- [ ] Lista filtra por profissional, tipo de consulta, dias atrasados.
- [ ] Status: cadência em andamento, exauriu cadência, agendou.
- [ ] Ação rápida: enviar mensagem manual, marcar como perdido.
- [ ] Exportação CSV.

**Resultado Esperado:** Visibilidade dos pacientes em risco de evasão.

---

## 8. Gestão de Receituários

### US-8.1: Cadastro de Receituário
**Como** Médico
**Quero** cadastrar uma receita vinculada ao paciente
**Para que** o sistema gerencie validade e renovação

**Critérios de Aceitação:**
- [ ] Campos: medicamentos, posologia, data de emissão, validade, tipo (comum, especial, controlada).
- [ ] Upload de PDF da receita (anexo, max 10MB).
- [ ] Vinculação obrigatória ao paciente e ao profissional emissor.
- [ ] Diferenciação de regras por tipo (controladas têm validade legal específica).
- [ ] Histórico de receitas por paciente acessível na timeline.

**Resultado Esperado:** Receituário registrado e rastreável.

---

### US-8.2: Alerta de Vencimento de Receita
**Como** Plataforma
**Quero** alertar antes do vencimento da receita
**Para que** o paciente renove sem ficar sem medicação

**Critérios de Aceitação:**
- [ ] Alertas configuráveis: 15, 7 e 1 dia antes (defaults).
- [ ] Mensagem proativa via IA oferecendo agendamento de renovação.
- [ ] Cancela cadência se paciente já agendou.
- [ ] Respeita janela de 24h do WhatsApp (template pré-aprovado).

**Resultado Esperado:** Renovação antecipada e oportunidade de reengajamento.

---

### US-8.3: Renovação via IA
**Como** Paciente
**Quero** que a IA me ofereça renovar a receita conversando comigo
**Para que** eu não precise ligar para marcar

**Critérios de Aceitação:**
- [ ] IA identifica receita prestes a vencer no contexto da conversa.
- [ ] Oferece agendamento de consulta de retorno apropriado.
- [ ] Vincula consulta agendada ao receituário em renovação.
- [ ] Médico vê o motivo "renovação" ao entrar na consulta.

**Resultado Esperado:** Ciclo de renovação automatizado ponta a ponta.

---

### US-8.4: Relatório de Receitas
**Como** Médico ou Admin Clínica
**Quero** consultar receitas vencidas, a vencer e renovadas
**Para que** eu acompanhe conformidade e oportunidade de retorno

**Critérios de Aceitação:**
- [ ] Filtros: status (vencida/a vencer/renovada), tipo, profissional, paciente.
- [ ] Indicador visual de criticidade (cores).
- [ ] Exportação CSV.

**Resultado Esperado:** Visão consolidada do ciclo de receituários.

---

## 9. Campanhas e Reativação

### US-9.1: Campanha de Reativação de Inativos
**Como** Admin Clínica
**Quero** disparar campanha para pacientes inativos há 6m+ ou 1a+
**Para que** eu reative base ociosa

**Critérios de Aceitação:**
- [ ] Segmentação por tempo de inatividade configurável.
- [ ] Filtros adicionais: tags, último profissional.
- [ ] Templates aprovados pela Meta usados fora da janela de 24h.
- [ ] Respeito a opt-in (LGPD): só envia para quem consentiu marketing.
- [ ] Relatório: enviados, entregues, lidos, respondidos, agendados.

**Resultado Esperado:** Base inativa sendo reaquecida com métricas claras.

---

### US-9.2: Campanha Sazonal
**Como** Admin Clínica
**Quero** disparar campanhas em datas específicas (vacinação, check-up)
**Para que** eu aproveite sazonalidade clínica

**Critérios de Aceitação:**
- [ ] Agendamento de envio futuro.
- [ ] Templates customizáveis por campanha.
- [ ] Segmentação por idade, sexo, tags, último procedimento.
- [ ] Pré-visualização antes de disparar.
- [ ] Limite de envio diário para não saturar canal.

**Resultado Esperado:** Campanhas operacionais sem ferramenta externa.

---

### US-9.3: Conformidade de Disparo (LGPD + Meta)
**Como** Plataforma
**Quero** garantir conformidade nos disparos em massa
**Para que** clínicas não sejam penalizadas

**Critérios de Aceitação:**
- [ ] Bloqueio de envio sem opt-in registrado.
- [ ] Apenas templates aprovados fora da janela de 24h.
- [ ] Link de descadastro em todas as mensagens não-transacionais.
- [ ] Respeito a horário comercial configurado.

**Resultado Esperado:** Operação dentro das normas Meta e LGPD.

---

## 10. Relatórios e Dashboard

### US-10.1: Dashboard Executivo
**Como** Admin Clínica ou Médico (proprietário)
**Quero** uma visão consolidada de KPIs
**Para que** eu acompanhe o negócio em tempo real

**Critérios de Aceitação:**
- [ ] Cards: leads por canal, taxa de conversão lead → consulta, no-show, NPS, faturamento estimado.
- [ ] Gráficos de tendência mensal.
- [ ] Filtro por período (7d, 30d, 90d, customizado).
- [ ] Exportação PDF do dashboard.

**Resultado Esperado:** Decisões baseadas em dados, não em sensação.

---

### US-10.2: Relatórios Operacionais
**Como** Admin Clínica
**Quero** acompanhar performance da operação de atendimento
**Para que** eu identifique gargalos e oportunidades

**Critérios de Aceitação:**
- [ ] Métricas: tempo médio de primeira resposta, tempo médio de resolução, volume por atendente, performance da IA (resoluções autônomas, escalonamentos).
- [ ] Drill-down por atendente e canal.
- [ ] Comparativo entre períodos.

**Resultado Esperado:** Gestão data-driven da operação.

---

### US-10.3: Relatórios Clínicos
**Como** Admin Clínica
**Quero** ver ocupação por profissional e mix de procedimentos
**Para que** eu otimize agenda e oferta

**Critérios de Aceitação:**
- [ ] Ocupação por profissional (taxa de horários preenchidos).
- [ ] Top tipos de procedimento.
- [ ] Retornos completados vs perdidos.
- [ ] Exportação CSV/PDF.

**Resultado Esperado:** Insights clínicos sem planilhas paralelas.

---

## 11. Integrações

### US-11.1: Webhooks de Eventos
**Como** Admin Clínica com sistemas próprios
**Quero** configurar webhooks para eventos do CRM
**Para que** eu integre com ferramentas externas

**Critérios de Aceitação:**
- [ ] Eventos suportados: novo lead, agendamento criado, agendamento cancelado, paciente alterado, receita emitida.
- [ ] Configuração de URL alvo + segredo (HMAC).
- [ ] Retry automático em falha (exponential backoff).
- [ ] Log de entregas com payload e resposta.

**Resultado Esperado:** Extensibilidade para integrações de terceiros.

---

### US-11.2: API Pública Documentada
**Como** Parceiro técnico
**Quero** consumir a API pública do CRM
**Para que** eu construa integrações sob medida

**Critérios de Aceitação:**
- [ ] Documentação OpenAPI/Swagger acessível.
- [ ] Autenticação via tokens Sanctum por tenant.
- [ ] Rate limiting por token.
- [ ] Versionamento (`/api/v1/...`).
- [ ] Endpoints cobrem: pacientes, agendamentos, mensagens, receituários.

**Resultado Esperado:** Plataforma extensível por terceiros.

---

## 12. Painel Super Admin (Plataforma)

### US-12.1: Gestão de Tenants
**Como** Super Admin
**Quero** listar e gerenciar todos os tenants
**Para que** eu opere a plataforma globalmente

**Critérios de Aceitação:**
- [ ] Listagem com filtros: status (trial/ativo/inadimplente/suspenso), plano, data de cadastro.
- [ ] Ações: suspender, reativar, cancelar, alterar plano manualmente.
- [ ] Acesso "impersonate" para suporte (auditado).
- [ ] Métricas por tenant: profissionais, consumo IA, MRR.

**Resultado Esperado:** Operação de plataforma centralizada.

---

### US-12.2: Configuração de Planos Globais
**Como** Super Admin
**Quero** criar e editar planos
**Para que** eu evolua a oferta comercial

**Critérios de Aceitação:**
- [ ] CRUD de planos: nome, preço base, profissionais inclusos, mensagens IA inclusas, valor por excedente, recursos habilitados.
- [ ] Plano marcado como ativo/inativo.
- [ ] Tenants existentes não impactados ao editar (versão snapshot).

**Resultado Esperado:** Catálogo comercial flexível.

---

### US-12.3: Métricas Globais da Plataforma
**Como** Super Admin
**Quero** ver KPIs globais
**Para que** eu acompanhe saúde do SaaS

**Critérios de Aceitação:**
- [ ] MRR, ARR, churn rate, número de tenants ativos, conversão trial → pago.
- [ ] Consumo total de mensagens IA (custo da plataforma).
- [ ] Gráficos de tendência.
- [ ] Alertas para anomalias (ex.: queda em conversão).

**Resultado Esperado:** Visão estratégica do negócio SaaS.

---

## 13. Privacidade, Segurança e LGPD

### US-13.1: Consentimento e Opt-in
**Como** Paciente
**Quero** dar/revogar consentimento para uso de dados e marketing
**Para que** meus direitos LGPD sejam respeitados

**Critérios de Aceitação:**
- [ ] Mensagem inicial em canais coleta consentimento explícito.
- [ ] Registro de data, canal e finalidade do consentimento.
- [ ] Comando "/sair" em qualquer canal revoga marketing.
- [ ] Painel de privacidade visível ao Admin Clínica para exportar registros.

**Resultado Esperado:** Conformidade LGPD comprovável por evidências.

---

### US-13.2: Direito ao Esquecimento
**Como** Paciente
**Quero** solicitar exclusão dos meus dados
**Para que** o tratamento cesse conforme LGPD

**Critérios de Aceitação:**
- [ ] Formulário/canal específico para solicitação.
- [ ] Anonimização (não exclusão física) preservando histórico de cobrança.
- [ ] Prazo de resposta máximo 15 dias úteis.
- [ ] Confirmação por e-mail ao requerente.

**Resultado Esperado:** Solicitações LGPD atendidas dentro do prazo legal.

---

### US-13.3: Pseudonimização de Prompts da IA
**Como** Plataforma
**Quero** remover dados pessoais antes de enviar contexto ao LLM
**Para que** dados sensíveis não vazem para o provedor de IA

**Critérios de Aceitação:**
- [ ] CPF, RG, número de carteirinha, telefone substituídos por placeholders.
- [ ] Mapeamento reversível mantido apenas em memória de processo.
- [ ] Logs do LLM não contêm dados pessoais identificáveis.
- [ ] Auditoria periódica para validar política.

**Resultado Esperado:** Redução de risco de exposição de dados sensíveis.

---

## Apêndice: Status das User Stories

| ID | Story | Prioridade | Status |
|----|-------|------------|--------|
| US-1.1 | Cadastro de Nova Clínica | Alta | Pendente |
| US-1.2 | Onboarding Guiado | Alta | Pendente |
| US-1.3 | Assinatura de Plano | Alta | Pendente |
| US-1.4 | Upgrade/Downgrade | Média | Pendente |
| US-1.5 | Monitoramento de Cota IA | Média | Pendente |
| US-2.1 | Login de Usuário | Alta | Pendente |
| US-2.2 | Cadastro de Usuários Internos | Alta | Pendente |
| US-2.3 | Recuperação de Senha | Média | Pendente |
| US-2.4 | Log de Auditoria | Média | Pendente |
| US-3.1 | Cadastro Manual de Paciente | Alta | Pendente |
| US-3.2 | Linha do Tempo do Paciente | Alta | Pendente |
| US-3.3 | Importação em Massa | Média | Pendente |
| US-3.4 | Funil de Leads (Kanban) | Alta | Pendente |
| US-3.5 | Tags e Status | Média | Pendente |
| US-4.1 | Conectar WhatsApp | Alta | Pendente |
| US-4.2 | Conectar Instagram | Média | Pendente |
| US-4.3 | Widget Web | Alta | Pendente |
| US-4.4 | Inbox Unificada | Alta | Pendente |
| US-4.5 | Atribuir/Transferir | Alta | Pendente |
| US-4.6 | Modo Humano Assume | Alta | Pendente |
| US-4.7 | Respostas Rápidas | Média | Pendente |
| US-5.1 | Base de Conhecimento IA | Alta | Pendente |
| US-5.2 | Classificação de Intenção | Alta | Pendente |
| US-5.3 | Agendamento via IA | Alta | Pendente |
| US-5.4 | Escalonamento Humano | Alta | Pendente |
| US-5.5 | Limites de Segurança Clínica | Alta | Pendente |
| US-5.6 | Treinamento Contínuo | Média | Pendente |
| US-5.7 | Auditoria da IA | Alta | Pendente |
| US-6.1 | Configurar Agenda | Alta | Pendente |
| US-6.2 | Tipos de Atendimento | Alta | Pendente |
| US-6.3 | Agendamento Manual | Alta | Pendente |
| US-6.4 | Confirmação Automática | Alta | Pendente |
| US-6.5 | Reagendamento via Chat | Alta | Pendente |
| US-6.6 | Lista de Espera | Média | Pendente |
| US-6.7 | Sync Google/Outlook | Média | Pendente |
| US-7.1 | Definir Prazo de Retorno | Alta | Pendente |
| US-7.2 | Disparo de Retorno | Alta | Pendente |
| US-7.3 | Relatório de Retornos | Média | Pendente |
| US-8.1 | Cadastro de Receituário | Alta | Pendente |
| US-8.2 | Alerta de Vencimento | Alta | Pendente |
| US-8.3 | Renovação via IA | Alta | Pendente |
| US-8.4 | Relatório de Receitas | Média | Pendente |
| US-9.1 | Reativação de Inativos | Média | Pendente |
| US-9.2 | Campanha Sazonal | Média | Pendente |
| US-9.3 | Conformidade de Disparo | Alta | Pendente |
| US-10.1 | Dashboard Executivo | Alta | Pendente |
| US-10.2 | Relatórios Operacionais | Média | Pendente |
| US-10.3 | Relatórios Clínicos | Média | Pendente |
| US-11.1 | Webhooks | Baixa | Pendente |
| US-11.2 | API Pública | Baixa | Pendente |
| US-12.1 | Gestão de Tenants | Alta | Pendente |
| US-12.2 | Planos Globais | Alta | Pendente |
| US-12.3 | Métricas Globais | Média | Pendente |
| US-13.1 | Consentimento LGPD | Alta | Pendente |
| US-13.2 | Direito ao Esquecimento | Alta | Pendente |
| US-13.3 | Pseudonimização IA | Alta | Pendente |
