<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Laravel API Documentation</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.style.css") }}" media="screen">
    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.print.css") }}" media="print">

    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>

    <link rel="stylesheet"
          href="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/styles/obsidian.min.css">
    <script src="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/highlight.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jets/0.14.1/jets.min.js"></script>

    <style id="language-style">
        /* starts out as display none and is replaced with js later  */
                    body .content .bash-example code { display: none; }
                    body .content .javascript-example code { display: none; }
            </style>


    <script src="{{ asset("/vendor/scribe/js/theme-default-5.10.0.js") }}"></script>

</head>

<body data-languages="[&quot;bash&quot;,&quot;javascript&quot;]">

<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{{ asset("/vendor/scribe/images/navbar.png") }}" alt="navbar-image"/>
    </span>
</a>
<div class="tocify-wrapper">
    
            <div class="lang-selector">
                                            <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                            <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                    </div>
    
    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Search">
    </div>

    <div id="toc">
                    <ul id="tocify-header-introduction" class="tocify-header">
                <li class="tocify-item level-1" data-unique="introduction">
                    <a href="#introduction">Introduction</a>
                </li>
                            </ul>
                    <ul id="tocify-header-authenticating-requests" class="tocify-header">
                <li class="tocify-item level-1" data-unique="authenticating-requests">
                    <a href="#authenticating-requests">Authenticating requests</a>
                </li>
                            </ul>
                    <ul id="tocify-header-audit" class="tocify-header">
                <li class="tocify-item level-1" data-unique="audit">
                    <a href="#audit">Audit</a>
                </li>
                                    <ul id="tocify-subheader-audit" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="audit-GETapi-v1-audit-logs-export">
                                <a href="#audit-GETapi-v1-audit-logs-export">Exportar logs como CSV.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="audit-GETapi-v1-audit-logs">
                                <a href="#audit-GETapi-v1-audit-logs">Lista paginada do log de auditoria.</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-auth" class="tocify-header">
                <li class="tocify-item level-1" data-unique="auth">
                    <a href="#auth">Auth</a>
                </li>
                                    <ul id="tocify-subheader-auth" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-login">
                                <a href="#auth-POSTapi-v1-auth-login">Login Bearer — emite Personal Access Token para o usuário.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-logout">
                                <a href="#auth-POSTapi-v1-auth-logout">Revogar apenas o token Bearer corrente.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-logout-all">
                                <a href="#auth-POSTapi-v1-auth-logout-all">Revogar todos os tokens Bearer do usuário.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-GETapi-v1-auth-me">
                                <a href="#auth-GETapi-v1-auth-me">Usuário autenticado, tenant e metadados do token corrente.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-GETapi-v1-auth-tokens">
                                <a href="#auth-GETapi-v1-auth-tokens">Listar todos os tokens do usuário com metadados.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-DELETEapi-v1-auth-tokens--tokenId-">
                                <a href="#auth-DELETEapi-v1-auth-tokens--tokenId-">Revogar token específico por ID (ownership enforced).</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-password-forgot">
                                <a href="#auth-POSTapi-v1-auth-password-forgot">Solicitar link de recuperação de senha.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-password-reset">
                                <a href="#auth-POSTapi-v1-auth-password-reset">Redefinir senha com token por e-mail.</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-billing" class="tocify-header">
                <li class="tocify-item level-1" data-unique="billing">
                    <a href="#billing">Billing</a>
                </li>
                                    <ul id="tocify-subheader-billing" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="billing-GETapi-v1-billing-plans">
                                <a href="#billing-GETapi-v1-billing-plans">Catálogo de planos disponíveis.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="billing-POSTapi-v1-billing-checkout">
                                <a href="#billing-POSTapi-v1-billing-checkout">Criar sessão de checkout Stripe.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="billing-GETapi-v1-billing-subscription">
                                <a href="#billing-GETapi-v1-billing-subscription">Estado atual da assinatura.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="billing-PATCHapi-v1-billing-subscription">
                                <a href="#billing-PATCHapi-v1-billing-subscription">Upgrade ou downgrade de plano/quantidade.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="billing-GETapi-v1-billing-ai-usage">
                                <a href="#billing-GETapi-v1-billing-ai-usage">Consumo de IA do ciclo atual.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="billing-PATCHapi-v1-billing-ai-usage-hard-cap">
                                <a href="#billing-PATCHapi-v1-billing-ai-usage-hard-cap">Configurar ou remover hard cap de mensagens IA.</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-crm-pacientes" class="tocify-header">
                <li class="tocify-item level-1" data-unique="crm-pacientes">
                    <a href="#crm-pacientes">CRM Pacientes</a>
                </li>
                                    <ul id="tocify-subheader-crm-pacientes" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="crm-pacientes-GETapi-v1-pacientes-exportar">
                                <a href="#crm-pacientes-GETapi-v1-pacientes-exportar">GET api/v1/pacientes/exportar</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-GETapi-v1-pacientes-importacao-template">
                                <a href="#crm-pacientes-GETapi-v1-pacientes-importacao-template">Baixa o template de importação em CSV ou XLSX.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-POSTapi-v1-pacientes-importacao">
                                <a href="#crm-pacientes-POSTapi-v1-pacientes-importacao">Inicia uma importação assíncrona de pacientes.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-GETapi-v1-pacientes-importacao--id-">
                                <a href="#crm-pacientes-GETapi-v1-pacientes-importacao--id-">Consulta o status de uma importação.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-GETapi-v1-funil-colunas">
                                <a href="#crm-pacientes-GETapi-v1-funil-colunas">Lista as colunas do funil do tenant, criando o template padrão na
primeira chamada (lazy init — AC-3.4.1).</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-PATCHapi-v1-funil-colunas--id-">
                                <a href="#crm-pacientes-PATCHapi-v1-funil-colunas--id-">Atualiza configuração de uma coluna (Admin only).</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-POSTapi-v1-pacientes-mesclagens">
                                <a href="#crm-pacientes-POSTapi-v1-pacientes-mesclagens">Executa a mesclagem de pacientes.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-POSTapi-v1-pacientes-mesclagens--id--reverter">
                                <a href="#crm-pacientes-POSTapi-v1-pacientes-mesclagens--id--reverter">Reverte uma mesclagem dentro do prazo de 30 dias.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-GETapi-v1-pacientes">
                                <a href="#crm-pacientes-GETapi-v1-pacientes">Lista pacientes com filtros e busca por similaridade.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-POSTapi-v1-pacientes">
                                <a href="#crm-pacientes-POSTapi-v1-pacientes">Cria um novo paciente.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-GETapi-v1-pacientes--id-">
                                <a href="#crm-pacientes-GETapi-v1-pacientes--id-">Retorna um paciente pelo ID (404 se não existe no tenant).</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-PATCHapi-v1-pacientes--id-">
                                <a href="#crm-pacientes-PATCHapi-v1-pacientes--id-">Atualiza parcialmente um paciente (PATCH).</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-DELETEapi-v1-pacientes--id-">
                                <a href="#crm-pacientes-DELETEapi-v1-pacientes--id-">Soft-delete do paciente.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-PATCHapi-v1-pacientes--id--status">
                                <a href="#crm-pacientes-PATCHapi-v1-pacientes--id--status">PATCH api/v1/pacientes/{id}/status</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-POSTapi-v1-pacientes--id--anonimizar">
                                <a href="#crm-pacientes-POSTapi-v1-pacientes--id--anonimizar">Anonimiza o paciente (LGPD — FR-035). Apenas Admin Clínica.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-GETapi-v1-pacientes--id--timeline">
                                <a href="#crm-pacientes-GETapi-v1-pacientes--id--timeline">GET api/v1/pacientes/{id}/timeline</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-GETapi-v1-pacientes--id--anotacoes">
                                <a href="#crm-pacientes-GETapi-v1-pacientes--id--anotacoes">Lista anotações do paciente visíveis para o usuário autenticado.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-POSTapi-v1-pacientes--id--anotacoes">
                                <a href="#crm-pacientes-POSTapi-v1-pacientes--id--anotacoes">Cria nova anotação para o paciente.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao">
                                <a href="#crm-pacientes-POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao">Retrata uma anotação existente (cria nova anotação linkada).</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-PATCHapi-v1-pacientes--id--funil">
                                <a href="#crm-pacientes-PATCHapi-v1-pacientes--id--funil">Move um paciente para outra coluna do funil (drag-and-drop ou automático).</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-POSTapi-v1-pacientes--id--tags">
                                <a href="#crm-pacientes-POSTapi-v1-pacientes--id--tags">POST api/v1/pacientes/{id}/tags</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-DELETEapi-v1-pacientes--id--tags--tag_id-">
                                <a href="#crm-pacientes-DELETEapi-v1-pacientes--id--tags--tag_id-">DELETE api/v1/pacientes/{id}/tags/{tag_id}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-GETapi-v1-tags">
                                <a href="#crm-pacientes-GETapi-v1-tags">GET api/v1/tags</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-POSTapi-v1-tags">
                                <a href="#crm-pacientes-POSTapi-v1-tags">POST api/v1/tags</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-GETapi-v1-convenios">
                                <a href="#crm-pacientes-GETapi-v1-convenios">GET api/v1/convenios</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-POSTapi-v1-convenios">
                                <a href="#crm-pacientes-POSTapi-v1-convenios">POST api/v1/convenios</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-PATCHapi-v1-convenios--id-">
                                <a href="#crm-pacientes-PATCHapi-v1-convenios--id-">PATCH api/v1/convenios/{id}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="crm-pacientes-DELETEapi-v1-convenios--id-">
                                <a href="#crm-pacientes-DELETEapi-v1-convenios--id-">DELETE api/v1/convenios/{id}</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-endpoints" class="tocify-header">
                <li class="tocify-item level-1" data-unique="endpoints">
                    <a href="#endpoints">Endpoints</a>
                </li>
                                    <ul id="tocify-subheader-endpoints" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-inbox-channels">
                                <a href="#endpoints-GETapi-v1-inbox-channels">GET api/v1/inbox/channels</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-inbox-channels">
                                <a href="#endpoints-POSTapi-v1-inbox-channels">POST api/v1/inbox/channels</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-inbox-channels--id-">
                                <a href="#endpoints-GETapi-v1-inbox-channels--id-">GET api/v1/inbox/channels/{id}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-PUTapi-v1-inbox-channels--id-">
                                <a href="#endpoints-PUTapi-v1-inbox-channels--id-">PUT api/v1/inbox/channels/{id}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-DELETEapi-v1-inbox-channels--id-">
                                <a href="#endpoints-DELETEapi-v1-inbox-channels--id-">DELETE api/v1/inbox/channels/{id}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-inbox-channels--channel--reconnect">
                                <a href="#endpoints-POSTapi-v1-inbox-channels--channel--reconnect">POST api/v1/inbox/channels/{channel}/reconnect</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-inbox-channels--channel_id--templates">
                                <a href="#endpoints-GETapi-v1-inbox-channels--channel_id--templates">GET api/v1/inbox/channels/{channel_id}/templates</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-inbox-channels--channel--templates-sync">
                                <a href="#endpoints-POSTapi-v1-inbox-channels--channel--templates-sync">POST api/v1/inbox/channels/{channel}/templates/sync</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-inbox-widget-configs--channelId-">
                                <a href="#endpoints-GETapi-v1-inbox-widget-configs--channelId-">GET api/v1/inbox/widget-configs/{channelId}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-PUTapi-v1-inbox-widget-configs--channelId-">
                                <a href="#endpoints-PUTapi-v1-inbox-widget-configs--channelId-">PUT api/v1/inbox/widget-configs/{channelId}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-inbox-widget-configs--channelId--snippet">
                                <a href="#endpoints-GETapi-v1-inbox-widget-configs--channelId--snippet">GET api/v1/inbox/widget-configs/{channelId}/snippet</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-inbox-conversations">
                                <a href="#endpoints-GETapi-v1-inbox-conversations">GET /inbox/conversations
Lista conversas do tenant com filtros e aggregations.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-inbox-conversations--id-">
                                <a href="#endpoints-GETapi-v1-inbox-conversations--id-">GET /inbox/conversations/{conversation}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-inbox-conversations--conversation_id--messages">
                                <a href="#endpoints-GETapi-v1-inbox-conversations--conversation_id--messages">GET /inbox/conversations/{conversation}/messages
Lista mensagens cursor-paginadas (DESC).</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-inbox-conversations--conversation_id--messages">
                                <a href="#endpoints-POSTapi-v1-inbox-conversations--conversation_id--messages">POST /inbox/conversations/{conversation}/messages
Envia mensagem outbound.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-inbox-conversations--conversation_id--read">
                                <a href="#endpoints-POSTapi-v1-inbox-conversations--conversation_id--read">POST /inbox/conversations/{conversation}/read</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-inbox-conversations--conversation_id--resolve">
                                <a href="#endpoints-POSTapi-v1-inbox-conversations--conversation_id--resolve">POST /inbox/conversations/{conversation}/resolve</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-inbox-conversations--conversation_id--reopen">
                                <a href="#endpoints-POSTapi-v1-inbox-conversations--conversation_id--reopen">POST /inbox/conversations/{conversation}/reopen</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-inbox-poll">
                                <a href="#endpoints-GETapi-v1-inbox-poll">GET api/v1/inbox/poll</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-inbox-conversations--conversation_id--takeover">
                                <a href="#endpoints-POSTapi-v1-inbox-conversations--conversation_id--takeover">POST /inbox/conversations/{conversation}/takeover</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-inbox-conversations--conversation_id--release-to-ai">
                                <a href="#endpoints-POSTapi-v1-inbox-conversations--conversation_id--release-to-ai">POST /inbox/conversations/{conversation}/release-to-ai</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-inbox-conversations--conversation_id--assign">
                                <a href="#endpoints-POSTapi-v1-inbox-conversations--conversation_id--assign">POST /inbox/conversations/{conversation}/assign</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-inbox-conversations--conversation_id--transfer">
                                <a href="#endpoints-POSTapi-v1-inbox-conversations--conversation_id--transfer">POST /inbox/conversations/{conversation}/transfer</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-inbox-conversations--conversation_id--assignments">
                                <a href="#endpoints-GETapi-v1-inbox-conversations--conversation_id--assignments">GET /inbox/conversations/{conversation}/assignments</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-inbox-assignment-rules">
                                <a href="#endpoints-GETapi-v1-inbox-assignment-rules">GET /inbox/assignment-rules</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-PUTapi-v1-inbox-assignment-rules">
                                <a href="#endpoints-PUTapi-v1-inbox-assignment-rules">PUT /inbox/assignment-rules</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-inbox-quick-replies">
                                <a href="#endpoints-GETapi-v1-inbox-quick-replies">GET /inbox/quick-replies</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-inbox-quick-replies">
                                <a href="#endpoints-POSTapi-v1-inbox-quick-replies">POST /inbox/quick-replies</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-PUTapi-v1-inbox-quick-replies--id-">
                                <a href="#endpoints-PUTapi-v1-inbox-quick-replies--id-">PATCH /inbox/quick-replies/{quickReply}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-DELETEapi-v1-inbox-quick-replies--id-">
                                <a href="#endpoints-DELETEapi-v1-inbox-quick-replies--id-">DELETE /inbox/quick-replies/{quickReply}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-inbox-quick-replies--quickReply_id--render">
                                <a href="#endpoints-POSTapi-v1-inbox-quick-replies--quickReply_id--render">POST /inbox/quick-replies/{quickReply}/render</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-inbox-media-upload">
                                <a href="#endpoints-POSTapi-v1-inbox-media-upload">POST /api/v1/inbox/media/upload</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-inbox-media--id-">
                                <a href="#endpoints-GETapi-v1-inbox-media--id-">GET /api/v1/inbox/media/{id}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-inbox-presence-heartbeat">
                                <a href="#endpoints-POSTapi-v1-inbox-presence-heartbeat">POST /api/v1/inbox/presence/heartbeat</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-inbox-presence">
                                <a href="#endpoints-GETapi-v1-inbox-presence">GET /api/v1/inbox/presence</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-PATCHapi-v1-inbox-presence-me">
                                <a href="#endpoints-PATCHapi-v1-inbox-presence-me">PATCH /api/v1/inbox/presence/me</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-webhooks-twilio-whatsapp">
                                <a href="#endpoints-POSTapi-v1-webhooks-twilio-whatsapp">POST api/v1/webhooks/twilio/whatsapp</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-webhooks-twilio-status">
                                <a href="#endpoints-POSTapi-v1-webhooks-twilio-status">POST api/v1/webhooks/twilio/status</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-webhooks-instagram">
                                <a href="#endpoints-GETapi-v1-webhooks-instagram">GET handshake — Meta confirma a URL do webhook antes de entregar eventos.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-webhooks-instagram">
                                <a href="#endpoints-POSTapi-v1-webhooks-instagram">POST inbound — processa DMs Instagram entregues pela Meta.</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-onboarding" class="tocify-header">
                <li class="tocify-item level-1" data-unique="onboarding">
                    <a href="#onboarding">Onboarding</a>
                </li>
                                    <ul id="tocify-subheader-onboarding" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="onboarding-GETapi-v1-onboarding-state">
                                <a href="#onboarding-GETapi-v1-onboarding-state">Estado atual do wizard de onboarding.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="onboarding-POSTapi-v1-onboarding-steps--stepKey--complete">
                                <a href="#onboarding-POSTapi-v1-onboarding-steps--stepKey--complete">Marcar etapa como concluída.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="onboarding-POSTapi-v1-onboarding-steps--stepKey--skip">
                                <a href="#onboarding-POSTapi-v1-onboarding-steps--stepKey--skip">Pular etapa não-bloqueante do wizard.</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-tenant" class="tocify-header">
                <li class="tocify-item level-1" data-unique="tenant">
                    <a href="#tenant">Tenant</a>
                </li>
                                    <ul id="tocify-subheader-tenant" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="tenant-POSTapi-v1-tenants-register">
                                <a href="#tenant-POSTapi-v1-tenants-register">Cadastro público de nova clínica (tenant).</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="tenant-GETapi-v1-tenant">
                                <a href="#tenant-GETapi-v1-tenant">Leitura do tenant atual.</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-users" class="tocify-header">
                <li class="tocify-item level-1" data-unique="users">
                    <a href="#users">Users</a>
                </li>
                                    <ul id="tocify-subheader-users" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="users-GETapi-v1-users">
                                <a href="#users-GETapi-v1-users">Listar usuários internos do tenant.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="users-PATCHapi-v1-users--id-">
                                <a href="#users-PATCHapi-v1-users--id-">Alterar perfil ou roles do usuário.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="users-DELETEapi-v1-users--id-">
                                <a href="#users-DELETEapi-v1-users--id-">Desativar usuário (soft-delete; preserva auditoria).</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="users-GETapi-v1-users-invitations">
                                <a href="#users-GETapi-v1-users-invitations">Listar convites pendentes.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="users-POSTapi-v1-users-invitations">
                                <a href="#users-POSTapi-v1-users-invitations">Enviar convite de novo usuário interno.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="users-DELETEapi-v1-users-invitations--id-">
                                <a href="#users-DELETEapi-v1-users-invitations--id-">Revogar convite pendente.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="users-POSTapi-v1-users-invitations-accept">
                                <a href="#users-POSTapi-v1-users-invitations-accept">Aceitar convite e definir senha.</a>
                            </li>
                                                                        </ul>
                            </ul>
            </div>

    <ul class="toc-footer" id="toc-footer">
                    <li style="padding-bottom: 5px;"><a href="{{ route("scribe.postman") }}">View Postman collection</a></li>
                            <li style="padding-bottom: 5px;"><a href="{{ route("scribe.openapi") }}">View OpenAPI spec</a></li>
                <li><a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a></li>
    </ul>

    <ul class="toc-footer" id="last-updated">
        <li>Last updated: May 13, 2026</li>
    </ul>
</div>

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        <h1 id="introduction">Introduction</h1>
<p>API REST do Paciente360 — CRM médico SaaS multi-tenant. A partir da Fase 4 (token auth migration), todos os endpoints autenticados usam Bearer Sanctum tokens e exigem o header <code>X-Tenant-Slug</code> para resolver o tenant alvo da request.</p>
<aside>
    <strong>Base URL</strong>: <code>https://9392-177-18-76-77.ngrok-free.app</code>
</aside>
<pre><code>Esta documentação cobre o pipeline pós-Fase 4 (Bearer). Para integrar:

1. `POST /api/v1/auth/login` — receba o token Bearer.
2. Envie `Authorization: Bearer &lt;token&gt;` + `X-Tenant-Slug: &lt;slug&gt;` em todas as requests autenticadas.
3. Gerencie sessões em `/api/v1/auth/tokens` (listagem) e `DELETE /api/v1/auth/tokens/{id}` (revogação).

Uma collection Postman oficial com pre-request scripts está disponível em `docs/api/Paciente360-API-v1.postman_collection.json` (auto-injeta Bearer + X-Tenant-Slug + salva o token após login).</code></pre>

        <h1 id="authenticating-requests">Authenticating requests</h1>
<p>To authenticate requests, include an <strong><code>Authorization</code></strong> header with the value <strong><code>"Bearer paciente360_&lt;seu-token&gt;"</code></strong>.</p>
<p>All authenticated endpoints are marked with a <code>requires authentication</code> badge in the documentation below.</p>
<p>Autentique-se com <code>POST /api/v1/auth/login</code> enviando <code>email</code> + <code>password</code>. A resposta inclui um campo <code>token</code> (Sanctum Personal Access Token, prefixo <code>paciente360_</code>). Inclua-o em <strong>todas</strong> as requests autenticadas:</p>
<pre><code>Authorization: Bearer paciente360_&lt;seu-token&gt;
X-Tenant-Slug: &lt;slug-da-clinica&gt;</code></pre>
<p>O header <code>X-Tenant-Slug</code> é obrigatório em rotas autenticadas (exceto <code>/auth/login</code>) — triple-check anti-token-roubo cross-tenant (FR-011 / Princípio II).</p>
<p>Tokens expiram em 30 dias com <em>sliding expiration</em>: cada request renova <code>expires_at</code> quando restam &lt; 5 dias.</p>

        <h1 id="audit">Audit</h1>

    

                                <h2 id="audit-GETapi-v1-audit-logs-export">Exportar logs como CSV.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Mesmos filtros do <code>index</code>, sem paginação. UTF-8 com BOM para
compatibilidade com Excel. Seguro contra formula injection.</p>

<span id="example-requests-GETapi-v1-audit-logs-export">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/audit-logs/export" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/audit-logs/export"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-audit-logs-export">
            <blockquote>
            <p>Example response (200, csv):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;Content-Type&quot;: &quot;text/csv&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-audit-logs-export" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-audit-logs-export"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-audit-logs-export"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-audit-logs-export" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-audit-logs-export">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-audit-logs-export" data-method="GET"
      data-path="api/v1/audit-logs/export"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-audit-logs-export', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/audit-logs/export</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-audit-logs-export"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-audit-logs-export"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-audit-logs-export"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="audit-GETapi-v1-audit-logs">Lista paginada do log de auditoria.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Suporta filtros por <code>from</code>, <code>to</code>, <code>action</code> e <code>actor_user_id</code>.
Máximo de 200 itens por página (cap silencioso).</p>

<span id="example-requests-GETapi-v1-audit-logs">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/audit-logs" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/audit-logs"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-audit-logs">
            <blockquote>
            <p>Example response (200, logs):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 1,
            &quot;action&quot;: &quot;auth.login&quot;,
            &quot;created_at&quot;: &quot;2026-05-10T10:00:00Z&quot;
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-audit-logs" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-audit-logs"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-audit-logs"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-audit-logs" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-audit-logs">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-audit-logs" data-method="GET"
      data-path="api/v1/audit-logs"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-audit-logs', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/audit-logs</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-audit-logs"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-audit-logs"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-audit-logs"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                <h1 id="auth">Auth</h1>

    

                                <h2 id="auth-POSTapi-v1-auth-login">Login Bearer — emite Personal Access Token para o usuário.</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-login">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/auth/login" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"gbailey@example.net\",
    \"password\": \"+-0pBNvYgxwmi\\/#iw\",
    \"device_name\": \"u\",
    \"remember\": true
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/auth/login"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "email": "gbailey@example.net",
    "password": "+-0pBNvYgxwmi\/#iw",
    "device_name": "u",
    "remember": true
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-login">
            <blockquote>
            <p>Example response (201, sucesso):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;token&quot;: &quot;paciente360_...&quot;,
    &quot;token_expires_at&quot;: &quot;2026-06-11T00:00:00+00:00&quot;,
    &quot;user&quot;: {},
    &quot;tenant&quot;: {}
}</code>
 </pre>
            <blockquote>
            <p>Example response (401, credenciais inválidas):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;invalid_credentials&quot;,
    &quot;message&quot;: &quot;Credenciais inv&aacute;lidas.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (423, conta bloqueada):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;account_locked&quot;,
    &quot;message&quot;: &quot;...&quot;,
    &quot;locked_until&quot;: &quot;ISO8601&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-auth-login" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-login"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-login"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-login" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-login">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-login" data-method="POST"
      data-path="api/v1/auth/login"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-login', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/login</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-login"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-login"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-login"
               value="gbailey@example.net"
               data-component="body">
    <br>
<p>Must be a valid email address. Must not be greater than 255 characters. Example: <code>gbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-v1-auth-login"
               value="+-0pBNvYgxwmi/#iw"
               data-component="body">
    <br>
<p>Must be at least 8 characters. Example: <code>+-0pBNvYgxwmi/#iw</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>device_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="device_name"                data-endpoint="POSTapi-v1-auth-login"
               value="u"
               data-component="body">
    <br>
<p>Must not be greater than 100 characters. Example: <code>u</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>remember</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-v1-auth-login" style="display: none">
            <input type="radio" name="remember"
                   value="true"
                   data-endpoint="POSTapi-v1-auth-login"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-v1-auth-login" style="display: none">
            <input type="radio" name="remember"
                   value="false"
                   data-endpoint="POSTapi-v1-auth-login"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Example: <code>true</code></p>
        </div>
        </form>

                    <h2 id="auth-POSTapi-v1-auth-logout">Revogar apenas o token Bearer corrente.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-auth-logout">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/auth/logout" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/auth/logout"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-logout">
            <blockquote>
            <p>Example response (204, sucesso):</p>
        </blockquote>
                <pre>
<code>Empty response</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-auth-logout" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-logout"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-logout"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-logout" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-logout">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-logout" data-method="POST"
      data-path="api/v1/auth/logout"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-logout', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/logout</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-auth-logout"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-logout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-logout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="auth-POSTapi-v1-auth-logout-all">Revogar todos os tokens Bearer do usuário.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-auth-logout-all">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/auth/logout-all" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/auth/logout-all"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-logout-all">
            <blockquote>
            <p>Example response (204, sucesso):</p>
        </blockquote>
                <pre>
<code>Empty response</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-auth-logout-all" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-logout-all"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-logout-all"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-logout-all" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-logout-all">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-logout-all" data-method="POST"
      data-path="api/v1/auth/logout-all"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-logout-all', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/logout-all</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-auth-logout-all"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-logout-all"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-logout-all"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="auth-GETapi-v1-auth-me">Usuário autenticado, tenant e metadados do token corrente.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-auth-me">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/auth/me" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/auth/me"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth-me">
            <blockquote>
            <p>Example response (200, sucesso):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;user&quot;: {},
    &quot;tenant&quot;: {},
    &quot;token&quot;: {
        &quot;id&quot;: 1,
        &quot;name&quot;: &quot;Default&quot;,
        &quot;abilities&quot;: [
            &quot;*&quot;
        ],
        &quot;last_used_at&quot;: null,
        &quot;expires_at&quot;: &quot;ISO8601&quot;
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-auth-me" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-auth-me"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth-me"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth-me" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth-me">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-auth-me" data-method="GET"
      data-path="api/v1/auth/me"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth-me', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/me</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-auth-me"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="auth-GETapi-v1-auth-tokens">Listar todos os tokens do usuário com metadados.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-auth-tokens">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/auth/tokens" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/auth/tokens"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth-tokens">
            <blockquote>
            <p>Example response (200, sucesso):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 1,
            &quot;name&quot;: &quot;Default&quot;,
            &quot;token_id_prefix&quot;: &quot;pacient3&quot;,
            &quot;abilities&quot;: [
                &quot;*&quot;
            ],
            &quot;last_used_at&quot;: null,
            &quot;expires_at&quot;: &quot;ISO8601&quot;,
            &quot;created_at&quot;: &quot;ISO8601&quot;,
            &quot;is_current&quot;: true
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-auth-tokens" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-auth-tokens"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth-tokens"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth-tokens" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth-tokens">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-auth-tokens" data-method="GET"
      data-path="api/v1/auth/tokens"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth-tokens', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/tokens</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-auth-tokens"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth-tokens"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth-tokens"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="auth-DELETEapi-v1-auth-tokens--tokenId-">Revogar token específico por ID (ownership enforced).</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retorna 404 quando o token não pertence ao usuário autenticado
(prevenção de enumeração cross-user via ownership check implícito).</p>

<span id="example-requests-DELETEapi-v1-auth-tokens--tokenId-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/auth/tokens/564" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/auth/tokens/564"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-auth-tokens--tokenId-">
            <blockquote>
            <p>Example response (204, sucesso):</p>
        </blockquote>
                <pre>
<code>Empty response</code>
 </pre>
            <blockquote>
            <p>Example response (404, token não encontrado ou de outro usuário):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;"></code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-v1-auth-tokens--tokenId-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-auth-tokens--tokenId-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-auth-tokens--tokenId-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-auth-tokens--tokenId-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-auth-tokens--tokenId-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-auth-tokens--tokenId-" data-method="DELETE"
      data-path="api/v1/auth/tokens/{tokenId}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-auth-tokens--tokenId-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/auth/tokens/{tokenId}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-auth-tokens--tokenId-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-auth-tokens--tokenId-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-auth-tokens--tokenId-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>tokenId</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="tokenId"                data-endpoint="DELETEapi-v1-auth-tokens--tokenId-"
               value="564"
               data-component="url">
    <br>
<p>Example: <code>564</code></p>
            </div>
                    </form>

                    <h2 id="auth-POSTapi-v1-auth-password-forgot">Solicitar link de recuperação de senha.</h2>

<p>
</p>

<p>Resposta sempre 202, mesmo se o e-mail não existir (FR-032 — anti-enumeração).</p>

<span id="example-requests-POSTapi-v1-auth-password-forgot">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/auth/password/forgot" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"gbailey@example.net\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/auth/password/forgot"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "email": "gbailey@example.net"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-password-forgot">
            <blockquote>
            <p>Example response (202, solicitação enviada):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;"></code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-auth-password-forgot" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-password-forgot"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-password-forgot"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-password-forgot" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-password-forgot">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-password-forgot" data-method="POST"
      data-path="api/v1/auth/password/forgot"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-password-forgot', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/password/forgot</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-password-forgot"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-password-forgot"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-password-forgot"
               value="gbailey@example.net"
               data-component="body">
    <br>
<p>Must be a valid email address. Must not be greater than 254 characters. Example: <code>gbailey@example.net</code></p>
        </div>
        </form>

                    <h2 id="auth-POSTapi-v1-auth-password-reset">Redefinir senha com token por e-mail.</h2>

<p>
</p>

<p>Token inválido ou expirado retorna 410 (via handler global).</p>

<span id="example-requests-POSTapi-v1-auth-password-reset">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/auth/password/reset" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"gbailey@example.net\",
    \"token\": \"miyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtnno\",
    \"password\": \"u.*,JHRp_B)L\'(?aiG;o\",
    \"password_confirmation\": \"architecto\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/auth/password/reset"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "email": "gbailey@example.net",
    "token": "miyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtnno",
    "password": "u.*,JHRp_B)L'(?aiG;o",
    "password_confirmation": "architecto"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-password-reset">
            <blockquote>
            <p>Example response (204, senha redefinida):</p>
        </blockquote>
                <pre>
<code>Empty response</code>
 </pre>
            <blockquote>
            <p>Example response (410, token inválido):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Token de redefini&ccedil;&atilde;o inv&aacute;lido ou expirado.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-auth-password-reset" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-password-reset"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-password-reset"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-password-reset" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-password-reset">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-password-reset" data-method="POST"
      data-path="api/v1/auth/password/reset"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-password-reset', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/password/reset</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-password-reset"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-password-reset"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-password-reset"
               value="gbailey@example.net"
               data-component="body">
    <br>
<p>Must be a valid email address. Example: <code>gbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="token"                data-endpoint="POSTapi-v1-auth-password-reset"
               value="miyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtnno"
               data-component="body">
    <br>
<p>Must be 64 characters. Example: <code>miyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtnno</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-v1-auth-password-reset"
               value="u.*,JHRp_B)L'(?aiG;o"
               data-component="body">
    <br>
<p>Must be at least 8 characters. Example: <code>u.*,JHRp_B)L'(?aiG;o</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password_confirmation</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password_confirmation"                data-endpoint="POSTapi-v1-auth-password-reset"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
        </form>

                <h1 id="billing">Billing</h1>

    

                                <h2 id="billing-GETapi-v1-billing-plans">Catálogo de planos disponíveis.</h2>

<p>
</p>

<p>Lista todos os planos ativos para exibição na landing page de preços.</p>

<span id="example-requests-GETapi-v1-billing-plans">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/billing/plans" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/billing/plans"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-billing-plans">
            <blockquote>
            <p>Example response (200, planos ativos):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">[
    {
        &quot;id&quot;: 1,
        &quot;code&quot;: &quot;starter&quot;,
        &quot;name&quot;: &quot;Starter&quot;
    }
]</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-billing-plans" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-billing-plans"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-billing-plans"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-billing-plans" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-billing-plans">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-billing-plans" data-method="GET"
      data-path="api/v1/billing/plans"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-billing-plans', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/billing/plans</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-billing-plans"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-billing-plans"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="billing-POSTapi-v1-billing-checkout">Criar sessão de checkout Stripe.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retorna a URL de checkout para o tenant assinar um plano.</p>

<span id="example-requests-POSTapi-v1-billing-checkout">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/billing/checkout" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"plan_code\": \"architecto\",
    \"professionals_quantity\": 22
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/billing/checkout"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "plan_code": "architecto",
    "professionals_quantity": 22
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-billing-checkout">
            <blockquote>
            <p>Example response (200, sessão criada):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;checkout_url&quot;: &quot;https://checkout.stripe.com/...&quot;,
    &quot;expires_at&quot;: &quot;2026-05-10T12:00:00Z&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (409, assinatura já existe):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Tenant j&aacute; possui assinatura ativa.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-billing-checkout" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-billing-checkout"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-billing-checkout"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-billing-checkout" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-billing-checkout">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-billing-checkout" data-method="POST"
      data-path="api/v1/billing/checkout"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-billing-checkout', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/billing/checkout</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-billing-checkout"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-billing-checkout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-billing-checkout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>plan_code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="plan_code"                data-endpoint="POSTapi-v1-billing-checkout"
               value="architecto"
               data-component="body">
    <br>
<p>The <code>code</code> of an existing record in the plans table. Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>professionals_quantity</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="professionals_quantity"                data-endpoint="POSTapi-v1-billing-checkout"
               value="22"
               data-component="body">
    <br>
<p>Must be at least 1. Example: <code>22</code></p>
        </div>
        </form>

                    <h2 id="billing-GETapi-v1-billing-subscription">Estado atual da assinatura.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retorna 404 se o tenant ainda estiver em trial sem assinatura formal.</p>

<span id="example-requests-GETapi-v1-billing-subscription">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/billing/subscription" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/billing/subscription"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-billing-subscription">
            <blockquote>
            <p>Example response (200, assinatura ativa):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: 1,
    &quot;stripe_status&quot;: &quot;active&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, sem assinatura):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;subscription_not_found&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-billing-subscription" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-billing-subscription"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-billing-subscription"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-billing-subscription" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-billing-subscription">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-billing-subscription" data-method="GET"
      data-path="api/v1/billing/subscription"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-billing-subscription', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/billing/subscription</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-billing-subscription"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-billing-subscription"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-billing-subscription"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="billing-PATCHapi-v1-billing-subscription">Upgrade ou downgrade de plano/quantidade.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Aumentos disparam proration imediata; reduções vigoram no próximo ciclo.</p>

<span id="example-requests-PATCHapi-v1-billing-subscription">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/billing/subscription" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"plan_code\": \"architecto\",
    \"professionals_quantity\": 22,
    \"proration_behavior\": \"none\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/billing/subscription"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "plan_code": "architecto",
    "professionals_quantity": 22,
    "proration_behavior": "none"
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PATCHapi-v1-billing-subscription">
            <blockquote>
            <p>Example response (200, atualizado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: 1,
    &quot;stripe_status&quot;: &quot;active&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (502, falha no Stripe):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Falha ao comunicar com gateway.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PATCHapi-v1-billing-subscription" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PATCHapi-v1-billing-subscription"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-v1-billing-subscription"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-v1-billing-subscription" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-v1-billing-subscription">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PATCHapi-v1-billing-subscription" data-method="PATCH"
      data-path="api/v1/billing/subscription"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-v1-billing-subscription', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/billing/subscription</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-v1-billing-subscription"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-v1-billing-subscription"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-v1-billing-subscription"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>plan_code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="plan_code"                data-endpoint="PATCHapi-v1-billing-subscription"
               value="architecto"
               data-component="body">
    <br>
<p>The <code>code</code> of an existing record in the plans table. Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>professionals_quantity</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="professionals_quantity"                data-endpoint="PATCHapi-v1-billing-subscription"
               value="22"
               data-component="body">
    <br>
<p>Must be at least 1. Example: <code>22</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>proration_behavior</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="proration_behavior"                data-endpoint="PATCHapi-v1-billing-subscription"
               value="none"
               data-component="body">
    <br>
<p>Example: <code>none</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>create_prorations</code></li> <li><code>none</code></li></ul>
        </div>
        </form>

                    <h2 id="billing-GETapi-v1-billing-ai-usage">Consumo de IA do ciclo atual.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retorna o meter de uso do mês corrente com projeção e dados de hard cap.</p>

<span id="example-requests-GETapi-v1-billing-ai-usage">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/billing/ai-usage" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/billing/ai-usage"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-billing-ai-usage">
            <blockquote>
            <p>Example response (200, meter atual):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;year_month&quot;: &quot;2026-05&quot;,
    &quot;consumed&quot;: 150,
    &quot;included_quota&quot;: 1000
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-billing-ai-usage" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-billing-ai-usage"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-billing-ai-usage"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-billing-ai-usage" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-billing-ai-usage">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-billing-ai-usage" data-method="GET"
      data-path="api/v1/billing/ai-usage"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-billing-ai-usage', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/billing/ai-usage</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-billing-ai-usage"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-billing-ai-usage"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-billing-ai-usage"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="billing-PATCHapi-v1-billing-ai-usage-hard-cap">Configurar ou remover hard cap de mensagens IA.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Passe <code>hard_cap: null</code> para remover o limite. <code>hard_cap: 0</code> desliga
a IA imediatamente.</p>

<span id="example-requests-PATCHapi-v1-billing-ai-usage-hard-cap">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/billing/ai-usage/hard-cap" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"hard_cap\": 27
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/billing/ai-usage/hard-cap"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "hard_cap": 27
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PATCHapi-v1-billing-ai-usage-hard-cap">
            <blockquote>
            <p>Example response (200, cap atualizado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;hard_cap&quot;: 500,
    &quot;consumed&quot;: 150
}</code>
 </pre>
    </span>
<span id="execution-results-PATCHapi-v1-billing-ai-usage-hard-cap" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PATCHapi-v1-billing-ai-usage-hard-cap"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-v1-billing-ai-usage-hard-cap"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-v1-billing-ai-usage-hard-cap" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-v1-billing-ai-usage-hard-cap">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PATCHapi-v1-billing-ai-usage-hard-cap" data-method="PATCH"
      data-path="api/v1/billing/ai-usage/hard-cap"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-v1-billing-ai-usage-hard-cap', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/billing/ai-usage/hard-cap</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-v1-billing-ai-usage-hard-cap"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-v1-billing-ai-usage-hard-cap"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-v1-billing-ai-usage-hard-cap"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>hard_cap</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="hard_cap"                data-endpoint="PATCHapi-v1-billing-ai-usage-hard-cap"
               value="27"
               data-component="body">
    <br>
<p>Must be at least 0. Example: <code>27</code></p>
        </div>
        </form>

                <h1 id="crm-pacientes">CRM Pacientes</h1>

    

                                <h2 id="crm-pacientes-GETapi-v1-pacientes-exportar">GET api/v1/pacientes/exportar</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-pacientes-exportar">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/exportar" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/exportar"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-pacientes-exportar">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-pacientes-exportar" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-pacientes-exportar"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-pacientes-exportar"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-pacientes-exportar" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-pacientes-exportar">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-pacientes-exportar" data-method="GET"
      data-path="api/v1/pacientes/exportar"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-pacientes-exportar', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/pacientes/exportar</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-pacientes-exportar"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-pacientes-exportar"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-pacientes-exportar"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="crm-pacientes-GETapi-v1-pacientes-importacao-template">Baixa o template de importação em CSV ou XLSX.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>GET /pacientes/importacao/template?formato=csv|xlsx</p>

<span id="example-requests-GETapi-v1-pacientes-importacao-template">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/importacao/template" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/importacao/template"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-pacientes-importacao-template">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-pacientes-importacao-template" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-pacientes-importacao-template"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-pacientes-importacao-template"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-pacientes-importacao-template" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-pacientes-importacao-template">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-pacientes-importacao-template" data-method="GET"
      data-path="api/v1/pacientes/importacao/template"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-pacientes-importacao-template', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/pacientes/importacao/template</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-pacientes-importacao-template"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-pacientes-importacao-template"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-pacientes-importacao-template"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="crm-pacientes-POSTapi-v1-pacientes-importacao">Inicia uma importação assíncrona de pacientes.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>POST /pacientes/importacao</p>

<span id="example-requests-POSTapi-v1-pacientes-importacao">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/importacao" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: multipart/form-data" \
    --header "Accept: application/json" \
    --form "status_inicial=ativo"\
    --form "arquivo=@/tmp/php7633mq57h06sbghLpO9" </code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/importacao"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "multipart/form-data",
    "Accept": "application/json",
};

const body = new FormData();
body.append('status_inicial', 'ativo');
body.append('arquivo', document.querySelector('input[name="arquivo"]').files[0]);

fetch(url, {
    method: "POST",
    headers,
    body,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-pacientes-importacao">
</span>
<span id="execution-results-POSTapi-v1-pacientes-importacao" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-pacientes-importacao"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-pacientes-importacao"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-pacientes-importacao" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-pacientes-importacao">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-pacientes-importacao" data-method="POST"
      data-path="api/v1/pacientes/importacao"
      data-authed="1"
      data-hasfiles="1"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-pacientes-importacao', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/pacientes/importacao</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-pacientes-importacao"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-pacientes-importacao"
               value="multipart/form-data"
               data-component="header">
    <br>
<p>Example: <code>multipart/form-data</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-pacientes-importacao"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>arquivo</code></b>&nbsp;&nbsp;
<small>file</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="file" style="display: none"
                              name="arquivo"                data-endpoint="POSTapi-v1-pacientes-importacao"
               value=""
               data-component="body">
    <br>
<p>Must be a file. Must not be greater than 5120 kilobytes. Example: <code>/tmp/php7633mq57h06sbghLpO9</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>status_inicial</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status_inicial"                data-endpoint="POSTapi-v1-pacientes-importacao"
               value="ativo"
               data-component="body">
    <br>
<p>Example: <code>ativo</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>lead</code></li> <li><code>ativo</code></li></ul>
        </div>
        </form>

                    <h2 id="crm-pacientes-GETapi-v1-pacientes-importacao--id-">Consulta o status de uma importação.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>GET /pacientes/importacao/{id}</p>

<span id="example-requests-GETapi-v1-pacientes-importacao--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/importacao/564" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/importacao/564"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-pacientes-importacao--id-">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-pacientes-importacao--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-pacientes-importacao--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-pacientes-importacao--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-pacientes-importacao--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-pacientes-importacao--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-pacientes-importacao--id-" data-method="GET"
      data-path="api/v1/pacientes/importacao/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-pacientes-importacao--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/pacientes/importacao/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-pacientes-importacao--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-pacientes-importacao--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-pacientes-importacao--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-v1-pacientes-importacao--id-"
               value="564"
               data-component="url">
    <br>
<p>The ID of the importacao. Example: <code>564</code></p>
            </div>
                    </form>

                    <h2 id="crm-pacientes-GETapi-v1-funil-colunas">Lista as colunas do funil do tenant, criando o template padrão na
primeira chamada (lazy init — AC-3.4.1).</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-funil-colunas">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/funil/colunas" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/funil/colunas"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-funil-colunas">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-funil-colunas" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-funil-colunas"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-funil-colunas"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-funil-colunas" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-funil-colunas">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-funil-colunas" data-method="GET"
      data-path="api/v1/funil/colunas"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-funil-colunas', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/funil/colunas</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-funil-colunas"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-funil-colunas"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-funil-colunas"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="crm-pacientes-PATCHapi-v1-funil-colunas--id-">Atualiza configuração de uma coluna (Admin only).</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retorna 409 se <code>posicao</code> colidir com outra coluna do mesmo tenant
(UNIQUE constraint em <code>(tenant_id, posicao)</code>).</p>

<span id="example-requests-PATCHapi-v1-funil-colunas--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/funil/colunas/564" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"nome\": \"b\",
    \"cor\": \"ngzmiyv\",
    \"motivo_obrigatorio\": true,
    \"posicao\": 26
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/funil/colunas/564"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "nome": "b",
    "cor": "ngzmiyv",
    "motivo_obrigatorio": true,
    "posicao": 26
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PATCHapi-v1-funil-colunas--id-">
</span>
<span id="execution-results-PATCHapi-v1-funil-colunas--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PATCHapi-v1-funil-colunas--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-v1-funil-colunas--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-v1-funil-colunas--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-v1-funil-colunas--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PATCHapi-v1-funil-colunas--id-" data-method="PATCH"
      data-path="api/v1/funil/colunas/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-v1-funil-colunas--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/funil/colunas/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-v1-funil-colunas--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-v1-funil-colunas--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-v1-funil-colunas--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PATCHapi-v1-funil-colunas--id-"
               value="564"
               data-component="url">
    <br>
<p>The ID of the coluna. Example: <code>564</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>nome</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="nome"                data-endpoint="PATCHapi-v1-funil-colunas--id-"
               value="b"
               data-component="body">
    <br>
<p>Must not be greater than 80 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>cor</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="cor"                data-endpoint="PATCHapi-v1-funil-colunas--id-"
               value="ngzmiyv"
               data-component="body">
    <br>
<p>Must not be greater than 7 characters. Example: <code>ngzmiyv</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>motivo_obrigatorio</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PATCHapi-v1-funil-colunas--id-" style="display: none">
            <input type="radio" name="motivo_obrigatorio"
                   value="true"
                   data-endpoint="PATCHapi-v1-funil-colunas--id-"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PATCHapi-v1-funil-colunas--id-" style="display: none">
            <input type="radio" name="motivo_obrigatorio"
                   value="false"
                   data-endpoint="PATCHapi-v1-funil-colunas--id-"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Example: <code>true</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>posicao</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="posicao"                data-endpoint="PATCHapi-v1-funil-colunas--id-"
               value="26"
               data-component="body">
    <br>
<p>Must be at least 1. Example: <code>26</code></p>
        </div>
        </form>

                    <h2 id="crm-pacientes-POSTapi-v1-pacientes-mesclagens">Executa a mesclagem de pacientes.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-pacientes-mesclagens">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/mesclagens" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"paciente_alvo_id\": 16,
    \"pacientes_origem_ids\": [
        16
    ],
    \"resolucoes\": [
        16
    ]
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/mesclagens"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "paciente_alvo_id": 16,
    "pacientes_origem_ids": [
        16
    ],
    "resolucoes": [
        16
    ]
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-pacientes-mesclagens">
</span>
<span id="execution-results-POSTapi-v1-pacientes-mesclagens" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-pacientes-mesclagens"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-pacientes-mesclagens"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-pacientes-mesclagens" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-pacientes-mesclagens">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-pacientes-mesclagens" data-method="POST"
      data-path="api/v1/pacientes/mesclagens"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-pacientes-mesclagens', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/pacientes/mesclagens</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-pacientes-mesclagens"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-pacientes-mesclagens"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-pacientes-mesclagens"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>paciente_alvo_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="paciente_alvo_id"                data-endpoint="POSTapi-v1-pacientes-mesclagens"
               value="16"
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the pacientes table. Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>pacientes_origem_ids</code></b>&nbsp;&nbsp;
<small>integer[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="pacientes_origem_ids[0]"                data-endpoint="POSTapi-v1-pacientes-mesclagens"
               data-component="body">
        <input type="number" style="display: none"
               name="pacientes_origem_ids[1]"                data-endpoint="POSTapi-v1-pacientes-mesclagens"
               data-component="body">
    <br>
<p>The value and <code>paciente_alvo_id</code> must be different. The <code>id</code> of an existing record in the pacientes table.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>resolucoes</code></b>&nbsp;&nbsp;
<small>integer[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="resolucoes[0]"                data-endpoint="POSTapi-v1-pacientes-mesclagens"
               data-component="body">
        <input type="number" style="display: none"
               name="resolucoes[1]"                data-endpoint="POSTapi-v1-pacientes-mesclagens"
               data-component="body">
    <br>

        </div>
        </form>

                    <h2 id="crm-pacientes-POSTapi-v1-pacientes-mesclagens--id--reverter">Reverte uma mesclagem dentro do prazo de 30 dias.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-pacientes-mesclagens--id--reverter">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/mesclagens/564/reverter" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/mesclagens/564/reverter"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-pacientes-mesclagens--id--reverter">
</span>
<span id="execution-results-POSTapi-v1-pacientes-mesclagens--id--reverter" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-pacientes-mesclagens--id--reverter"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-pacientes-mesclagens--id--reverter"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-pacientes-mesclagens--id--reverter" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-pacientes-mesclagens--id--reverter">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-pacientes-mesclagens--id--reverter" data-method="POST"
      data-path="api/v1/pacientes/mesclagens/{id}/reverter"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-pacientes-mesclagens--id--reverter', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/pacientes/mesclagens/{id}/reverter</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-pacientes-mesclagens--id--reverter"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-pacientes-mesclagens--id--reverter"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-pacientes-mesclagens--id--reverter"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="POSTapi-v1-pacientes-mesclagens--id--reverter"
               value="564"
               data-component="url">
    <br>
<p>The ID of the mesclagen. Example: <code>564</code></p>
            </div>
                    </form>

                    <h2 id="crm-pacientes-GETapi-v1-pacientes">Lista pacientes com filtros e busca por similaridade.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Query params:</p>
<ul>
<li><code>q</code>: busca fuzzy por nome ou telefone (mín. 2 chars)</li>
<li><code>status</code>: filtro por status</li>
<li><code>per_page</code>: tamanho da página (max 100)</li>
</ul>

<span id="example-requests-GETapi-v1-pacientes">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"q\": \"bngz\",
    \"status\": \"ativo\",
    \"origem\": \"architecto\",
    \"funil_coluna_id\": 16,
    \"profissional_responsavel_id\": 16,
    \"tag\": \"ngzmiyvdljnikhwa\",
    \"per_page\": 24
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "q": "bngz",
    "status": "ativo",
    "origem": "architecto",
    "funil_coluna_id": 16,
    "profissional_responsavel_id": 16,
    "tag": "ngzmiyvdljnikhwa",
    "per_page": 24
};

fetch(url, {
    method: "GET",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-pacientes">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-pacientes" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-pacientes"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-pacientes"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-pacientes" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-pacientes">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-pacientes" data-method="GET"
      data-path="api/v1/pacientes"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-pacientes', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/pacientes</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-pacientes"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-pacientes"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-pacientes"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>q</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="q"                data-endpoint="GETapi-v1-pacientes"
               value="bngz"
               data-component="body">
    <br>
<p>Must be at least 2 characters. Example: <code>bngz</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="GETapi-v1-pacientes"
               value="ativo"
               data-component="body">
    <br>
<p>Example: <code>ativo</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>lead</code></li> <li><code>ativo</code></li> <li><code>inativo</code></li> <li><code>bloqueado</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>origem</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="origem"                data-endpoint="GETapi-v1-pacientes"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>funil_coluna_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="funil_coluna_id"                data-endpoint="GETapi-v1-pacientes"
               value="16"
               data-component="body">
    <br>
<p>Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>profissional_responsavel_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="profissional_responsavel_id"                data-endpoint="GETapi-v1-pacientes"
               value="16"
               data-component="body">
    <br>
<p>Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>tag</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="tag"                data-endpoint="GETapi-v1-pacientes"
               value="ngzmiyvdljnikhwa"
               data-component="body">
    <br>
<p>Must be at least 1 character. Example: <code>ngzmiyvdljnikhwa</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-v1-pacientes"
               value="24"
               data-component="body">
    <br>
<p>Must be at least 1. Must not be greater than 500. Example: <code>24</code></p>
        </div>
        </form>

                    <h2 id="crm-pacientes-POSTapi-v1-pacientes">Cria um novo paciente.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-pacientes">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"nome\": \"b\",
    \"cpf\": \"ngzmiyvdljn\",
    \"documento_estrangeiro\": \"i\",
    \"data_nascimento\": \"2022-06-06\",
    \"telefone_primario\": \"ngzmiyvdljnikhwa\",
    \"telefones_secundarios\": [
        \"ykcmyuwpwlvqwrsi\"
    ],
    \"email\": \"pfritsch@example.com\",
    \"endereco\": {
        \"logradouro\": \"c\",
        \"numero\": \"qldzsnrwtujwvlxj\",
        \"complemento\": \"k\",
        \"bairro\": \"l\",
        \"cidade\": \"q\",
        \"estado\": \"pp\",
        \"cep\": \"wqbewt\"
    },
    \"status\": \"lead\",
    \"origem\": \"indicacao\",
    \"origem_detalhe\": \"n\",
    \"profissional_responsavel_id\": 16,
    \"tags\": [
        16
    ],
    \"ignorar_duplicata\": false,
    \"q\": \"architecto\",
    \"convenios\": [
        {
            \"convenio_id\": 16,
            \"papel\": \"secundario\",
            \"numero_carteirinha\": \"n\"
        }
    ]
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "nome": "b",
    "cpf": "ngzmiyvdljn",
    "documento_estrangeiro": "i",
    "data_nascimento": "2022-06-06",
    "telefone_primario": "ngzmiyvdljnikhwa",
    "telefones_secundarios": [
        "ykcmyuwpwlvqwrsi"
    ],
    "email": "pfritsch@example.com",
    "endereco": {
        "logradouro": "c",
        "numero": "qldzsnrwtujwvlxj",
        "complemento": "k",
        "bairro": "l",
        "cidade": "q",
        "estado": "pp",
        "cep": "wqbewt"
    },
    "status": "lead",
    "origem": "indicacao",
    "origem_detalhe": "n",
    "profissional_responsavel_id": 16,
    "tags": [
        16
    ],
    "ignorar_duplicata": false,
    "q": "architecto",
    "convenios": [
        {
            "convenio_id": 16,
            "papel": "secundario",
            "numero_carteirinha": "n"
        }
    ]
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-pacientes">
</span>
<span id="execution-results-POSTapi-v1-pacientes" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-pacientes"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-pacientes"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-pacientes" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-pacientes">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-pacientes" data-method="POST"
      data-path="api/v1/pacientes"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-pacientes', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/pacientes</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-pacientes"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-pacientes"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-pacientes"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>nome</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="nome"                data-endpoint="POSTapi-v1-pacientes"
               value="b"
               data-component="body">
    <br>
<p>Must be at least 2 characters. Must not be greater than 150 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>cpf</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="cpf"                data-endpoint="POSTapi-v1-pacientes"
               value="ngzmiyvdljn"
               data-component="body">
    <br>
<p>Must be 11 characters. Example: <code>ngzmiyvdljn</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>documento_estrangeiro</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="documento_estrangeiro"                data-endpoint="POSTapi-v1-pacientes"
               value="i"
               data-component="body">
    <br>
<p>Must not be greater than 30 characters. Example: <code>i</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>data_nascimento</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="data_nascimento"                data-endpoint="POSTapi-v1-pacientes"
               value="2022-06-06"
               data-component="body">
    <br>
<p>Must be a valid date. Must be a date before <code>today</code>. Example: <code>2022-06-06</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>telefone_primario</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="telefone_primario"                data-endpoint="POSTapi-v1-pacientes"
               value="ngzmiyvdljnikhwa"
               data-component="body">
    <br>
<p>Must not be greater than 20 characters. Example: <code>ngzmiyvdljnikhwa</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>telefones_secundarios</code></b>&nbsp;&nbsp;
<small>string[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="telefones_secundarios[0]"                data-endpoint="POSTapi-v1-pacientes"
               data-component="body">
        <input type="text" style="display: none"
               name="telefones_secundarios[1]"                data-endpoint="POSTapi-v1-pacientes"
               data-component="body">
    <br>
<p>Must not be greater than 20 characters.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-pacientes"
               value="pfritsch@example.com"
               data-component="body">
    <br>
<p>Must be a valid email address. Must not be greater than 254 characters. Example: <code>pfritsch@example.com</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>endereco</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
<br>

            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>logradouro</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="endereco.logradouro"                data-endpoint="POSTapi-v1-pacientes"
               value="c"
               data-component="body">
    <br>
<p>Must not be greater than 200 characters. Example: <code>c</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>numero</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="endereco.numero"                data-endpoint="POSTapi-v1-pacientes"
               value="qldzsnrwtujwvlxj"
               data-component="body">
    <br>
<p>Must not be greater than 20 characters. Example: <code>qldzsnrwtujwvlxj</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>complemento</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="endereco.complemento"                data-endpoint="POSTapi-v1-pacientes"
               value="k"
               data-component="body">
    <br>
<p>Must not be greater than 100 characters. Example: <code>k</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>bairro</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="endereco.bairro"                data-endpoint="POSTapi-v1-pacientes"
               value="l"
               data-component="body">
    <br>
<p>Must not be greater than 100 characters. Example: <code>l</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>cidade</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="endereco.cidade"                data-endpoint="POSTapi-v1-pacientes"
               value="q"
               data-component="body">
    <br>
<p>Must not be greater than 100 characters. Example: <code>q</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>estado</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="endereco.estado"                data-endpoint="POSTapi-v1-pacientes"
               value="pp"
               data-component="body">
    <br>
<p>Must be 2 characters. Example: <code>pp</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>cep</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="endereco.cep"                data-endpoint="POSTapi-v1-pacientes"
               value="wqbewt"
               data-component="body">
    <br>
<p>Must not be greater than 10 characters. Example: <code>wqbewt</code></p>
                    </div>
                                    </details>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="POSTapi-v1-pacientes"
               value="lead"
               data-component="body">
    <br>
<p>Example: <code>lead</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>lead</code></li> <li><code>ativo</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>origem</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="origem"                data-endpoint="POSTapi-v1-pacientes"
               value="indicacao"
               data-component="body">
    <br>
<p>Example: <code>indicacao</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>site</code></li> <li><code>indicacao</code></li> <li><code>whatsapp</code></li> <li><code>instagram</code></li> <li><code>telefone</code></li> <li><code>presencial</code></li> <li><code>outro</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>origem_detalhe</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="origem_detalhe"                data-endpoint="POSTapi-v1-pacientes"
               value="n"
               data-component="body">
    <br>
<p>Must not be greater than 500 characters. Example: <code>n</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>profissional_responsavel_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="profissional_responsavel_id"                data-endpoint="POSTapi-v1-pacientes"
               value="16"
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the professionals table. Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>convenios</code></b>&nbsp;&nbsp;
<small>object[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
<br>
<p>Must not have more than 2 items.</p>
            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>convenio_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="convenios.0.convenio_id"                data-endpoint="POSTapi-v1-pacientes"
               value="16"
               data-component="body">
    <br>
<p>This field is required when <code>convenios</code> is present. The <code>id</code> of an existing record in the convenios table. Example: <code>16</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>papel</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="convenios.0.papel"                data-endpoint="POSTapi-v1-pacientes"
               value="secundario"
               data-component="body">
    <br>
<p>Example: <code>secundario</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>principal</code></li> <li><code>secundario</code></li></ul>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>numero_carteirinha</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="convenios.0.numero_carteirinha"                data-endpoint="POSTapi-v1-pacientes"
               value="n"
               data-component="body">
    <br>
<p>Must not be greater than 30 characters. Example: <code>n</code></p>
                    </div>
                                    </details>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>tags</code></b>&nbsp;&nbsp;
<small>integer[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="tags[0]"                data-endpoint="POSTapi-v1-pacientes"
               data-component="body">
        <input type="number" style="display: none"
               name="tags[1]"                data-endpoint="POSTapi-v1-pacientes"
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the tags table.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>ignorar_duplicata</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-v1-pacientes" style="display: none">
            <input type="radio" name="ignorar_duplicata"
                   value="true"
                   data-endpoint="POSTapi-v1-pacientes"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-v1-pacientes" style="display: none">
            <input type="radio" name="ignorar_duplicata"
                   value="false"
                   data-endpoint="POSTapi-v1-pacientes"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Example: <code>false</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>q</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="q"                data-endpoint="POSTapi-v1-pacientes"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
        </form>

                    <h2 id="crm-pacientes-GETapi-v1-pacientes--id-">Retorna um paciente pelo ID (404 se não existe no tenant).</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-pacientes--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-pacientes--id-">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-pacientes--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-pacientes--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-pacientes--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-pacientes--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-pacientes--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-pacientes--id-" data-method="GET"
      data-path="api/v1/pacientes/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-pacientes--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/pacientes/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-pacientes--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-pacientes--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-pacientes--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-v1-pacientes--id-"
               value="564"
               data-component="url">
    <br>
<p>The ID of the paciente. Example: <code>564</code></p>
            </div>
                    </form>

                    <h2 id="crm-pacientes-PATCHapi-v1-pacientes--id-">Atualiza parcialmente um paciente (PATCH).</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PATCHapi-v1-pacientes--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"nome\": \"b\",
    \"cpf\": \"ngzmiyvdljn\",
    \"documento_estrangeiro\": \"i\",
    \"data_nascimento\": \"2022-06-06\",
    \"telefone_primario\": \"ngzmiyvdljnikhwa\",
    \"telefones_secundarios\": [
        \"ykcmyuwpwlvqwrsi\"
    ],
    \"email\": \"pfritsch@example.com\",
    \"endereco\": {
        \"logradouro\": \"c\",
        \"numero\": \"qldzsnrwtujwvlxj\",
        \"complemento\": \"k\",
        \"bairro\": \"l\",
        \"cidade\": \"q\",
        \"estado\": \"pp\",
        \"cep\": \"wqbewt\"
    },
    \"origem\": \"instagram\",
    \"origem_detalhe\": \"n\",
    \"profissional_responsavel_id\": 16,
    \"tags\": [
        16
    ],
    \"convenios\": [
        {
            \"convenio_id\": 16,
            \"papel\": \"principal\",
            \"numero_carteirinha\": \"n\"
        }
    ]
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "nome": "b",
    "cpf": "ngzmiyvdljn",
    "documento_estrangeiro": "i",
    "data_nascimento": "2022-06-06",
    "telefone_primario": "ngzmiyvdljnikhwa",
    "telefones_secundarios": [
        "ykcmyuwpwlvqwrsi"
    ],
    "email": "pfritsch@example.com",
    "endereco": {
        "logradouro": "c",
        "numero": "qldzsnrwtujwvlxj",
        "complemento": "k",
        "bairro": "l",
        "cidade": "q",
        "estado": "pp",
        "cep": "wqbewt"
    },
    "origem": "instagram",
    "origem_detalhe": "n",
    "profissional_responsavel_id": 16,
    "tags": [
        16
    ],
    "convenios": [
        {
            "convenio_id": 16,
            "papel": "principal",
            "numero_carteirinha": "n"
        }
    ]
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PATCHapi-v1-pacientes--id-">
</span>
<span id="execution-results-PATCHapi-v1-pacientes--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PATCHapi-v1-pacientes--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-v1-pacientes--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-v1-pacientes--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-v1-pacientes--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PATCHapi-v1-pacientes--id-" data-method="PATCH"
      data-path="api/v1/pacientes/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-v1-pacientes--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/pacientes/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-v1-pacientes--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="564"
               data-component="url">
    <br>
<p>The ID of the paciente. Example: <code>564</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>nome</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="nome"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="b"
               data-component="body">
    <br>
<p>Must be at least 2 characters. Must not be greater than 150 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>cpf</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="cpf"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="ngzmiyvdljn"
               data-component="body">
    <br>
<p>Must be 11 characters. Example: <code>ngzmiyvdljn</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>documento_estrangeiro</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="documento_estrangeiro"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="i"
               data-component="body">
    <br>
<p>Must not be greater than 30 characters. Example: <code>i</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>data_nascimento</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="data_nascimento"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="2022-06-06"
               data-component="body">
    <br>
<p>Must be a valid date. Must be a date before <code>today</code>. Example: <code>2022-06-06</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>telefone_primario</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="telefone_primario"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="ngzmiyvdljnikhwa"
               data-component="body">
    <br>
<p>Must not be greater than 20 characters. Example: <code>ngzmiyvdljnikhwa</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>telefones_secundarios</code></b>&nbsp;&nbsp;
<small>string[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="telefones_secundarios[0]"                data-endpoint="PATCHapi-v1-pacientes--id-"
               data-component="body">
        <input type="text" style="display: none"
               name="telefones_secundarios[1]"                data-endpoint="PATCHapi-v1-pacientes--id-"
               data-component="body">
    <br>
<p>Must not be greater than 20 characters.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="pfritsch@example.com"
               data-component="body">
    <br>
<p>Must be a valid email address. Must not be greater than 254 characters. Example: <code>pfritsch@example.com</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>endereco</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
<br>

            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>logradouro</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="endereco.logradouro"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="c"
               data-component="body">
    <br>
<p>Must not be greater than 200 characters. Example: <code>c</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>numero</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="endereco.numero"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="qldzsnrwtujwvlxj"
               data-component="body">
    <br>
<p>Must not be greater than 20 characters. Example: <code>qldzsnrwtujwvlxj</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>complemento</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="endereco.complemento"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="k"
               data-component="body">
    <br>
<p>Must not be greater than 100 characters. Example: <code>k</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>bairro</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="endereco.bairro"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="l"
               data-component="body">
    <br>
<p>Must not be greater than 100 characters. Example: <code>l</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>cidade</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="endereco.cidade"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="q"
               data-component="body">
    <br>
<p>Must not be greater than 100 characters. Example: <code>q</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>estado</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="endereco.estado"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="pp"
               data-component="body">
    <br>
<p>Must be 2 characters. Example: <code>pp</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>cep</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="endereco.cep"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="wqbewt"
               data-component="body">
    <br>
<p>Must not be greater than 10 characters. Example: <code>wqbewt</code></p>
                    </div>
                                    </details>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>origem</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="origem"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="instagram"
               data-component="body">
    <br>
<p>Example: <code>instagram</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>site</code></li> <li><code>indicacao</code></li> <li><code>whatsapp</code></li> <li><code>instagram</code></li> <li><code>telefone</code></li> <li><code>presencial</code></li> <li><code>outro</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>origem_detalhe</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="origem_detalhe"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="n"
               data-component="body">
    <br>
<p>Must not be greater than 500 characters. Example: <code>n</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>profissional_responsavel_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="profissional_responsavel_id"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="16"
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the professionals table. Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>convenios</code></b>&nbsp;&nbsp;
<small>object[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
<br>
<p>Must not have more than 2 items.</p>
            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>convenio_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="convenios.0.convenio_id"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="16"
               data-component="body">
    <br>
<p>This field is required when <code>convenios</code> is present. The <code>id</code> of an existing record in the convenios table. Example: <code>16</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>papel</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="convenios.0.papel"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="principal"
               data-component="body">
    <br>
<p>Example: <code>principal</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>principal</code></li> <li><code>secundario</code></li></ul>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>numero_carteirinha</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="convenios.0.numero_carteirinha"                data-endpoint="PATCHapi-v1-pacientes--id-"
               value="n"
               data-component="body">
    <br>
<p>Must not be greater than 30 characters. Example: <code>n</code></p>
                    </div>
                                    </details>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>tags</code></b>&nbsp;&nbsp;
<small>integer[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="tags[0]"                data-endpoint="PATCHapi-v1-pacientes--id-"
               data-component="body">
        <input type="number" style="display: none"
               name="tags[1]"                data-endpoint="PATCHapi-v1-pacientes--id-"
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the tags table.</p>
        </div>
        </form>

                    <h2 id="crm-pacientes-DELETEapi-v1-pacientes--id-">Soft-delete do paciente.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-DELETEapi-v1-pacientes--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-pacientes--id-">
</span>
<span id="execution-results-DELETEapi-v1-pacientes--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-pacientes--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-pacientes--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-pacientes--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-pacientes--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-pacientes--id-" data-method="DELETE"
      data-path="api/v1/pacientes/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-pacientes--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/pacientes/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-pacientes--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-pacientes--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-pacientes--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-v1-pacientes--id-"
               value="564"
               data-component="url">
    <br>
<p>The ID of the paciente. Example: <code>564</code></p>
            </div>
                    </form>

                    <h2 id="crm-pacientes-PATCHapi-v1-pacientes--id--status">PATCH api/v1/pacientes/{id}/status</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PATCHapi-v1-pacientes--id--status">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/status" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"status\": \"inativo\",
    \"motivo\": \"b\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/status"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "status": "inativo",
    "motivo": "b"
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PATCHapi-v1-pacientes--id--status">
</span>
<span id="execution-results-PATCHapi-v1-pacientes--id--status" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PATCHapi-v1-pacientes--id--status"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-v1-pacientes--id--status"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-v1-pacientes--id--status" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-v1-pacientes--id--status">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PATCHapi-v1-pacientes--id--status" data-method="PATCH"
      data-path="api/v1/pacientes/{id}/status"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-v1-pacientes--id--status', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/pacientes/{id}/status</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-v1-pacientes--id--status"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-v1-pacientes--id--status"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-v1-pacientes--id--status"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PATCHapi-v1-pacientes--id--status"
               value="564"
               data-component="url">
    <br>
<p>The ID of the paciente. Example: <code>564</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="PATCHapi-v1-pacientes--id--status"
               value="inativo"
               data-component="body">
    <br>
<p>Example: <code>inativo</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>lead</code></li> <li><code>ativo</code></li> <li><code>inativo</code></li> <li><code>bloqueado</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>motivo</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="motivo"                data-endpoint="PATCHapi-v1-pacientes--id--status"
               value="b"
               data-component="body">
    <br>
<p>Must not be greater than 500 characters. Example: <code>b</code></p>
        </div>
        </form>

                    <h2 id="crm-pacientes-POSTapi-v1-pacientes--id--anonimizar">Anonimiza o paciente (LGPD — FR-035). Apenas Admin Clínica.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-pacientes--id--anonimizar">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/anonimizar" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/anonimizar"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-pacientes--id--anonimizar">
</span>
<span id="execution-results-POSTapi-v1-pacientes--id--anonimizar" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-pacientes--id--anonimizar"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-pacientes--id--anonimizar"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-pacientes--id--anonimizar" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-pacientes--id--anonimizar">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-pacientes--id--anonimizar" data-method="POST"
      data-path="api/v1/pacientes/{id}/anonimizar"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-pacientes--id--anonimizar', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/pacientes/{id}/anonimizar</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-pacientes--id--anonimizar"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-pacientes--id--anonimizar"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-pacientes--id--anonimizar"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="POSTapi-v1-pacientes--id--anonimizar"
               value="564"
               data-component="url">
    <br>
<p>The ID of the paciente. Example: <code>564</code></p>
            </div>
                    </form>

                    <h2 id="crm-pacientes-GETapi-v1-pacientes--id--timeline">GET api/v1/pacientes/{id}/timeline</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-pacientes--id--timeline">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/timeline" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/timeline"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-pacientes--id--timeline">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-pacientes--id--timeline" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-pacientes--id--timeline"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-pacientes--id--timeline"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-pacientes--id--timeline" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-pacientes--id--timeline">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-pacientes--id--timeline" data-method="GET"
      data-path="api/v1/pacientes/{id}/timeline"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-pacientes--id--timeline', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/pacientes/{id}/timeline</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-pacientes--id--timeline"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-pacientes--id--timeline"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-pacientes--id--timeline"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-v1-pacientes--id--timeline"
               value="564"
               data-component="url">
    <br>
<p>The ID of the paciente. Example: <code>564</code></p>
            </div>
                    </form>

                    <h2 id="crm-pacientes-GETapi-v1-pacientes--id--anotacoes">Lista anotações do paciente visíveis para o usuário autenticado.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>GET /pacientes/{id}/anotacoes</p>

<span id="example-requests-GETapi-v1-pacientes--id--anotacoes">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/anotacoes" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/anotacoes"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-pacientes--id--anotacoes">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-pacientes--id--anotacoes" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-pacientes--id--anotacoes"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-pacientes--id--anotacoes"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-pacientes--id--anotacoes" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-pacientes--id--anotacoes">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-pacientes--id--anotacoes" data-method="GET"
      data-path="api/v1/pacientes/{id}/anotacoes"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-pacientes--id--anotacoes', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/pacientes/{id}/anotacoes</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-pacientes--id--anotacoes"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-pacientes--id--anotacoes"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-pacientes--id--anotacoes"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-v1-pacientes--id--anotacoes"
               value="564"
               data-component="url">
    <br>
<p>The ID of the paciente. Example: <code>564</code></p>
            </div>
                    </form>

                    <h2 id="crm-pacientes-POSTapi-v1-pacientes--id--anotacoes">Cria nova anotação para o paciente.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>POST /pacientes/{id}/anotacoes</p>

<span id="example-requests-POSTapi-v1-pacientes--id--anotacoes">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/anotacoes" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"tipo\": \"geral\",
    \"texto\": \"b\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/anotacoes"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "tipo": "geral",
    "texto": "b"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-pacientes--id--anotacoes">
</span>
<span id="execution-results-POSTapi-v1-pacientes--id--anotacoes" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-pacientes--id--anotacoes"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-pacientes--id--anotacoes"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-pacientes--id--anotacoes" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-pacientes--id--anotacoes">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-pacientes--id--anotacoes" data-method="POST"
      data-path="api/v1/pacientes/{id}/anotacoes"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-pacientes--id--anotacoes', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/pacientes/{id}/anotacoes</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-pacientes--id--anotacoes"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-pacientes--id--anotacoes"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-pacientes--id--anotacoes"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="POSTapi-v1-pacientes--id--anotacoes"
               value="564"
               data-component="url">
    <br>
<p>The ID of the paciente. Example: <code>564</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>tipo</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="tipo"                data-endpoint="POSTapi-v1-pacientes--id--anotacoes"
               value="geral"
               data-component="body">
    <br>
<p>Example: <code>geral</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>geral</code></li> <li><code>clinica</code></li> <li><code>comportamental</code></li> <li><code>financeira</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>texto</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="texto"                data-endpoint="POSTapi-v1-pacientes--id--anotacoes"
               value="b"
               data-component="body">
    <br>
<p>Must be at least 1 character. Must not be greater than 5000 characters. Example: <code>b</code></p>
        </div>
        </form>

                    <h2 id="crm-pacientes-POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao">Retrata uma anotação existente (cria nova anotação linkada).</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>POST /pacientes/{id}/anotacoes/{anotacao_id}/retratacao</p>

<span id="example-requests-POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/anotacoes/564/retratacao" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"texto\": \"b\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/anotacoes/564/retratacao"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "texto": "b"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao">
</span>
<span id="execution-results-POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao" data-method="POST"
      data-path="api/v1/pacientes/{id}/anotacoes/{anotacao_id}/retratacao"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/pacientes/{id}/anotacoes/{anotacao_id}/retratacao</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao"
               value="564"
               data-component="url">
    <br>
<p>The ID of the paciente. Example: <code>564</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>anotacao_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="anotacao_id"                data-endpoint="POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao"
               value="564"
               data-component="url">
    <br>
<p>The ID of the anotacao. Example: <code>564</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>texto</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="texto"                data-endpoint="POSTapi-v1-pacientes--id--anotacoes--anotacao_id--retratacao"
               value="b"
               data-component="body">
    <br>
<p>Must be at least 10 characters. Must not be greater than 5000 characters. Example: <code>b</code></p>
        </div>
        </form>

                    <h2 id="crm-pacientes-PATCHapi-v1-pacientes--id--funil">Move um paciente para outra coluna do funil (drag-and-drop ou automático).</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PATCHapi-v1-pacientes--id--funil">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/funil" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"coluna_id\": 16,
    \"posicao\": 39,
    \"motivo\": \"outro\",
    \"motivo_outro\": \"g\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/funil"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "coluna_id": 16,
    "posicao": 39,
    "motivo": "outro",
    "motivo_outro": "g"
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PATCHapi-v1-pacientes--id--funil">
</span>
<span id="execution-results-PATCHapi-v1-pacientes--id--funil" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PATCHapi-v1-pacientes--id--funil"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-v1-pacientes--id--funil"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-v1-pacientes--id--funil" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-v1-pacientes--id--funil">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PATCHapi-v1-pacientes--id--funil" data-method="PATCH"
      data-path="api/v1/pacientes/{id}/funil"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-v1-pacientes--id--funil', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/pacientes/{id}/funil</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-v1-pacientes--id--funil"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-v1-pacientes--id--funil"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-v1-pacientes--id--funil"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PATCHapi-v1-pacientes--id--funil"
               value="564"
               data-component="url">
    <br>
<p>The ID of the paciente. Example: <code>564</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>coluna_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="coluna_id"                data-endpoint="PATCHapi-v1-pacientes--id--funil"
               value="16"
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the funil_colunas table. Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>posicao</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="posicao"                data-endpoint="PATCHapi-v1-pacientes--id--funil"
               value="39"
               data-component="body">
    <br>
<p>Must be at least 0. Example: <code>39</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>motivo</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="motivo"                data-endpoint="PATCHapi-v1-pacientes--id--funil"
               value="outro"
               data-component="body">
    <br>
<p>Example: <code>outro</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>sem_interesse</code></li> <li><code>sem_retorno</code></li> <li><code>preco</code></li> <li><code>outro</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>motivo_outro</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="motivo_outro"                data-endpoint="PATCHapi-v1-pacientes--id--funil"
               value="g"
               data-component="body">
    <br>
<p>This field is required when <code>motivo</code> is <code>outro</code>. Must be at least 10 characters. Must not be greater than 255 characters. Example: <code>g</code></p>
        </div>
        </form>

                    <h2 id="crm-pacientes-POSTapi-v1-pacientes--id--tags">POST api/v1/pacientes/{id}/tags</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-pacientes--id--tags">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/tags" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"tag_id\": 16
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/tags"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "tag_id": 16
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-pacientes--id--tags">
</span>
<span id="execution-results-POSTapi-v1-pacientes--id--tags" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-pacientes--id--tags"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-pacientes--id--tags"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-pacientes--id--tags" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-pacientes--id--tags">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-pacientes--id--tags" data-method="POST"
      data-path="api/v1/pacientes/{id}/tags"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-pacientes--id--tags', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/pacientes/{id}/tags</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-pacientes--id--tags"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-pacientes--id--tags"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-pacientes--id--tags"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="POSTapi-v1-pacientes--id--tags"
               value="564"
               data-component="url">
    <br>
<p>The ID of the paciente. Example: <code>564</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>tag_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="tag_id"                data-endpoint="POSTapi-v1-pacientes--id--tags"
               value="16"
               data-component="body">
    <br>
<p>Example: <code>16</code></p>
        </div>
        </form>

                    <h2 id="crm-pacientes-DELETEapi-v1-pacientes--id--tags--tag_id-">DELETE api/v1/pacientes/{id}/tags/{tag_id}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-DELETEapi-v1-pacientes--id--tags--tag_id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/tags/564" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/pacientes/564/tags/564"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-pacientes--id--tags--tag_id-">
</span>
<span id="execution-results-DELETEapi-v1-pacientes--id--tags--tag_id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-pacientes--id--tags--tag_id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-pacientes--id--tags--tag_id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-pacientes--id--tags--tag_id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-pacientes--id--tags--tag_id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-pacientes--id--tags--tag_id-" data-method="DELETE"
      data-path="api/v1/pacientes/{id}/tags/{tag_id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-pacientes--id--tags--tag_id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/pacientes/{id}/tags/{tag_id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-pacientes--id--tags--tag_id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-pacientes--id--tags--tag_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-pacientes--id--tags--tag_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-v1-pacientes--id--tags--tag_id-"
               value="564"
               data-component="url">
    <br>
<p>The ID of the paciente. Example: <code>564</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>tag_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="tag_id"                data-endpoint="DELETEapi-v1-pacientes--id--tags--tag_id-"
               value="564"
               data-component="url">
    <br>
<p>The ID of the tag. Example: <code>564</code></p>
            </div>
                    </form>

                    <h2 id="crm-pacientes-GETapi-v1-tags">GET api/v1/tags</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-tags">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/tags" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"tipo\": \"sistemica\",
    \"q\": \"bngzmiyvdljnikhw\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/tags"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "tipo": "sistemica",
    "q": "bngzmiyvdljnikhw"
};

fetch(url, {
    method: "GET",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-tags">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-tags" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-tags"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-tags"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-tags" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-tags">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-tags" data-method="GET"
      data-path="api/v1/tags"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-tags', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/tags</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-tags"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-tags"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-tags"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>tipo</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="tipo"                data-endpoint="GETapi-v1-tags"
               value="sistemica"
               data-component="body">
    <br>
<p>Example: <code>sistemica</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>livre</code></li> <li><code>sistemica</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>q</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="q"                data-endpoint="GETapi-v1-tags"
               value="bngzmiyvdljnikhw"
               data-component="body">
    <br>
<p>Must be at least 1 character. Example: <code>bngzmiyvdljnikhw</code></p>
        </div>
        </form>

                    <h2 id="crm-pacientes-POSTapi-v1-tags">POST api/v1/tags</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-tags">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/tags" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"nome\": \"b\",
    \"cor\": \"#fEEeDb\",
    \"descricao\": \"l\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/tags"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "nome": "b",
    "cor": "#fEEeDb",
    "descricao": "l"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-tags">
</span>
<span id="execution-results-POSTapi-v1-tags" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-tags"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-tags"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-tags" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-tags">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-tags" data-method="POST"
      data-path="api/v1/tags"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-tags', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/tags</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-tags"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-tags"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-tags"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>nome</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="nome"                data-endpoint="POSTapi-v1-tags"
               value="b"
               data-component="body">
    <br>
<p>Must be at least 2 characters. Must not be greater than 50 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>cor</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="cor"                data-endpoint="POSTapi-v1-tags"
               value="#fEEeDb"
               data-component="body">
    <br>
<p>Must match the regex /^#[0-9A-Fa-f]{6}$/. Example: <code>#fEEeDb</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>descricao</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="descricao"                data-endpoint="POSTapi-v1-tags"
               value="l"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>l</code></p>
        </div>
        </form>

                    <h2 id="crm-pacientes-GETapi-v1-convenios">GET api/v1/convenios</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-convenios">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/convenios" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/convenios"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-convenios">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-convenios" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-convenios"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-convenios"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-convenios" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-convenios">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-convenios" data-method="GET"
      data-path="api/v1/convenios"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-convenios', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/convenios</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-convenios"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-convenios"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-convenios"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="crm-pacientes-POSTapi-v1-convenios">POST api/v1/convenios</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-convenios">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/convenios" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"nome\": \"b\",
    \"codigo_ans\": \"ngzmiy\",
    \"is_active\": false
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/convenios"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "nome": "b",
    "codigo_ans": "ngzmiy",
    "is_active": false
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-convenios">
</span>
<span id="execution-results-POSTapi-v1-convenios" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-convenios"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-convenios"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-convenios" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-convenios">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-convenios" data-method="POST"
      data-path="api/v1/convenios"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-convenios', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/convenios</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-convenios"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-convenios"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-convenios"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>nome</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="nome"                data-endpoint="POSTapi-v1-convenios"
               value="b"
               data-component="body">
    <br>
<p>Must be at least 2 characters. Must not be greater than 150 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>codigo_ans</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="codigo_ans"                data-endpoint="POSTapi-v1-convenios"
               value="ngzmiy"
               data-component="body">
    <br>
<p>Must not be greater than 10 characters. Example: <code>ngzmiy</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_active</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-v1-convenios" style="display: none">
            <input type="radio" name="is_active"
                   value="true"
                   data-endpoint="POSTapi-v1-convenios"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-v1-convenios" style="display: none">
            <input type="radio" name="is_active"
                   value="false"
                   data-endpoint="POSTapi-v1-convenios"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Example: <code>false</code></p>
        </div>
        </form>

                    <h2 id="crm-pacientes-PATCHapi-v1-convenios--id-">PATCH api/v1/convenios/{id}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PATCHapi-v1-convenios--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/convenios/564" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"nome\": \"b\",
    \"codigo_ans\": \"ngzmiy\",
    \"is_active\": true
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/convenios/564"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "nome": "b",
    "codigo_ans": "ngzmiy",
    "is_active": true
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PATCHapi-v1-convenios--id-">
</span>
<span id="execution-results-PATCHapi-v1-convenios--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PATCHapi-v1-convenios--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-v1-convenios--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-v1-convenios--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-v1-convenios--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PATCHapi-v1-convenios--id-" data-method="PATCH"
      data-path="api/v1/convenios/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-v1-convenios--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/convenios/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-v1-convenios--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-v1-convenios--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-v1-convenios--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PATCHapi-v1-convenios--id-"
               value="564"
               data-component="url">
    <br>
<p>The ID of the convenio. Example: <code>564</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>nome</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="nome"                data-endpoint="PATCHapi-v1-convenios--id-"
               value="b"
               data-component="body">
    <br>
<p>Must be at least 2 characters. Must not be greater than 150 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>codigo_ans</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="codigo_ans"                data-endpoint="PATCHapi-v1-convenios--id-"
               value="ngzmiy"
               data-component="body">
    <br>
<p>Must not be greater than 10 characters. Example: <code>ngzmiy</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_active</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PATCHapi-v1-convenios--id-" style="display: none">
            <input type="radio" name="is_active"
                   value="true"
                   data-endpoint="PATCHapi-v1-convenios--id-"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PATCHapi-v1-convenios--id-" style="display: none">
            <input type="radio" name="is_active"
                   value="false"
                   data-endpoint="PATCHapi-v1-convenios--id-"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Example: <code>true</code></p>
        </div>
        </form>

                    <h2 id="crm-pacientes-DELETEapi-v1-convenios--id-">DELETE api/v1/convenios/{id}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-DELETEapi-v1-convenios--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/convenios/564" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/convenios/564"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-convenios--id-">
</span>
<span id="execution-results-DELETEapi-v1-convenios--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-convenios--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-convenios--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-convenios--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-convenios--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-convenios--id-" data-method="DELETE"
      data-path="api/v1/convenios/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-convenios--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/convenios/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-convenios--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-convenios--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-convenios--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-v1-convenios--id-"
               value="564"
               data-component="url">
    <br>
<p>The ID of the convenio. Example: <code>564</code></p>
            </div>
                    </form>

                <h1 id="endpoints">Endpoints</h1>

    

                                <h2 id="endpoints-GETapi-v1-inbox-channels">GET api/v1/inbox/channels</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-inbox-channels">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/channels" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/channels"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-inbox-channels">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-inbox-channels" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-inbox-channels"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-inbox-channels"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-inbox-channels" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-inbox-channels">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-inbox-channels" data-method="GET"
      data-path="api/v1/inbox/channels"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-inbox-channels', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/inbox/channels</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-inbox-channels"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-inbox-channels"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-inbox-channels"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-POSTapi-v1-inbox-channels">POST api/v1/inbox/channels</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-inbox-channels">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/channels" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"type\": \"instagram\",
    \"name\": \"b\",
    \"credentials\": []
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/channels"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "type": "instagram",
    "name": "b",
    "credentials": []
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-inbox-channels">
</span>
<span id="execution-results-POSTapi-v1-inbox-channels" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-inbox-channels"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-inbox-channels"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-inbox-channels" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-inbox-channels">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-inbox-channels" data-method="POST"
      data-path="api/v1/inbox/channels"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-inbox-channels', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/inbox/channels</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-inbox-channels"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-inbox-channels"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-inbox-channels"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="type"                data-endpoint="POSTapi-v1-inbox-channels"
               value="instagram"
               data-component="body">
    <br>
<p>Example: <code>instagram</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>whatsapp</code></li> <li><code>instagram</code></li> <li><code>web</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-v1-inbox-channels"
               value="b"
               data-component="body">
    <br>
<p>Must be at least 2 characters. Must not be greater than 100 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>credentials</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="credentials"                data-endpoint="POSTapi-v1-inbox-channels"
               value=""
               data-component="body">
    <br>

        </div>
        </form>

                    <h2 id="endpoints-GETapi-v1-inbox-channels--id-">GET api/v1/inbox/channels/{id}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-inbox-channels--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/channels/architecto" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/channels/architecto"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-inbox-channels--id-">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-inbox-channels--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-inbox-channels--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-inbox-channels--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-inbox-channels--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-inbox-channels--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-inbox-channels--id-" data-method="GET"
      data-path="api/v1/inbox/channels/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-inbox-channels--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/inbox/channels/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-inbox-channels--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-inbox-channels--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-inbox-channels--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-v1-inbox-channels--id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the channel. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-PUTapi-v1-inbox-channels--id-">PUT api/v1/inbox/channels/{id}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PUTapi-v1-inbox-channels--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/channels/architecto" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"b\",
    \"auto_send_disabled\": true
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/channels/architecto"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "b",
    "auto_send_disabled": true
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-inbox-channels--id-">
</span>
<span id="execution-results-PUTapi-v1-inbox-channels--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-inbox-channels--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-inbox-channels--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-inbox-channels--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-inbox-channels--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-inbox-channels--id-" data-method="PUT"
      data-path="api/v1/inbox/channels/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-inbox-channels--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/inbox/channels/{id}</code></b>
        </p>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/inbox/channels/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-inbox-channels--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-inbox-channels--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-inbox-channels--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PUTapi-v1-inbox-channels--id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the channel. Example: <code>architecto</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="PUTapi-v1-inbox-channels--id-"
               value="b"
               data-component="body">
    <br>
<p>Must be at least 2 characters. Must not be greater than 100 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>auto_send_disabled</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PUTapi-v1-inbox-channels--id-" style="display: none">
            <input type="radio" name="auto_send_disabled"
                   value="true"
                   data-endpoint="PUTapi-v1-inbox-channels--id-"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PUTapi-v1-inbox-channels--id-" style="display: none">
            <input type="radio" name="auto_send_disabled"
                   value="false"
                   data-endpoint="PUTapi-v1-inbox-channels--id-"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Example: <code>true</code></p>
        </div>
        </form>

                    <h2 id="endpoints-DELETEapi-v1-inbox-channels--id-">DELETE api/v1/inbox/channels/{id}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-DELETEapi-v1-inbox-channels--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/channels/architecto" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/channels/architecto"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-inbox-channels--id-">
</span>
<span id="execution-results-DELETEapi-v1-inbox-channels--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-inbox-channels--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-inbox-channels--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-inbox-channels--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-inbox-channels--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-inbox-channels--id-" data-method="DELETE"
      data-path="api/v1/inbox/channels/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-inbox-channels--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/inbox/channels/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-inbox-channels--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-inbox-channels--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-inbox-channels--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-v1-inbox-channels--id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the channel. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-POSTapi-v1-inbox-channels--channel--reconnect">POST api/v1/inbox/channels/{channel}/reconnect</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-inbox-channels--channel--reconnect">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/channels/564/reconnect" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/channels/564/reconnect"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-inbox-channels--channel--reconnect">
</span>
<span id="execution-results-POSTapi-v1-inbox-channels--channel--reconnect" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-inbox-channels--channel--reconnect"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-inbox-channels--channel--reconnect"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-inbox-channels--channel--reconnect" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-inbox-channels--channel--reconnect">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-inbox-channels--channel--reconnect" data-method="POST"
      data-path="api/v1/inbox/channels/{channel}/reconnect"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-inbox-channels--channel--reconnect', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/inbox/channels/{channel}/reconnect</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-inbox-channels--channel--reconnect"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-inbox-channels--channel--reconnect"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-inbox-channels--channel--reconnect"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>channel</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="channel"                data-endpoint="POSTapi-v1-inbox-channels--channel--reconnect"
               value="564"
               data-component="url">
    <br>
<p>The channel. Example: <code>564</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>credentials_override</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="credentials_override"                data-endpoint="POSTapi-v1-inbox-channels--channel--reconnect"
               value=""
               data-component="body">
    <br>

        </div>
        </form>

                    <h2 id="endpoints-GETapi-v1-inbox-channels--channel_id--templates">GET api/v1/inbox/channels/{channel_id}/templates</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-inbox-channels--channel_id--templates">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/channels/architecto/templates" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/channels/architecto/templates"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-inbox-channels--channel_id--templates">
            <blockquote>
            <p>Example response (404):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;The route api/v1/inbox/channels/architecto/templates could not be found.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-inbox-channels--channel_id--templates" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-inbox-channels--channel_id--templates"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-inbox-channels--channel_id--templates"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-inbox-channels--channel_id--templates" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-inbox-channels--channel_id--templates">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-inbox-channels--channel_id--templates" data-method="GET"
      data-path="api/v1/inbox/channels/{channel_id}/templates"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-inbox-channels--channel_id--templates', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/inbox/channels/{channel_id}/templates</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-inbox-channels--channel_id--templates"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-inbox-channels--channel_id--templates"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-inbox-channels--channel_id--templates"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>channel_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="channel_id"                data-endpoint="GETapi-v1-inbox-channels--channel_id--templates"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the channel. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-POSTapi-v1-inbox-channels--channel--templates-sync">POST api/v1/inbox/channels/{channel}/templates/sync</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-inbox-channels--channel--templates-sync">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/channels/564/templates/sync" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/channels/564/templates/sync"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-inbox-channels--channel--templates-sync">
</span>
<span id="execution-results-POSTapi-v1-inbox-channels--channel--templates-sync" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-inbox-channels--channel--templates-sync"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-inbox-channels--channel--templates-sync"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-inbox-channels--channel--templates-sync" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-inbox-channels--channel--templates-sync">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-inbox-channels--channel--templates-sync" data-method="POST"
      data-path="api/v1/inbox/channels/{channel}/templates/sync"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-inbox-channels--channel--templates-sync', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/inbox/channels/{channel}/templates/sync</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-inbox-channels--channel--templates-sync"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-inbox-channels--channel--templates-sync"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-inbox-channels--channel--templates-sync"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>channel</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="channel"                data-endpoint="POSTapi-v1-inbox-channels--channel--templates-sync"
               value="564"
               data-component="url">
    <br>
<p>The channel. Example: <code>564</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-v1-inbox-widget-configs--channelId-">GET api/v1/inbox/widget-configs/{channelId}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-inbox-widget-configs--channelId-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/widget-configs/564" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/widget-configs/564"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-inbox-widget-configs--channelId-">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-inbox-widget-configs--channelId-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-inbox-widget-configs--channelId-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-inbox-widget-configs--channelId-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-inbox-widget-configs--channelId-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-inbox-widget-configs--channelId-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-inbox-widget-configs--channelId-" data-method="GET"
      data-path="api/v1/inbox/widget-configs/{channelId}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-inbox-widget-configs--channelId-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/inbox/widget-configs/{channelId}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-inbox-widget-configs--channelId-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-inbox-widget-configs--channelId-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-inbox-widget-configs--channelId-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>channelId</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="channelId"                data-endpoint="GETapi-v1-inbox-widget-configs--channelId-"
               value="564"
               data-component="url">
    <br>
<p>Example: <code>564</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-PUTapi-v1-inbox-widget-configs--channelId-">PUT api/v1/inbox/widget-configs/{channelId}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PUTapi-v1-inbox-widget-configs--channelId-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/widget-configs/564" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"appearance\": {
        \"primary_color\": \"#FeeEd\",
        \"logo_url\": \"http:\\/\\/www.dach.com\\/mollitia-modi-deserunt-aut-ab-provident-perspiciatis-quo.html\",
        \"position\": \"bottom-left\",
        \"button_label\": \"m\"
    },
    \"initial_message\": \"y\",
    \"business_hours\": {
        \"monday\": \"25:59-31:42\",
        \"tuesday\": \"25:59-31:42\",
        \"wednesday\": \"25:59-31:42\",
        \"thursday\": \"25:59-31:42\",
        \"friday\": \"25:59-31:42\",
        \"saturday\": \"25:59-31:42\",
        \"sunday\": \"25:59-31:42\",
        \"timezone\": \"Asia\\/Sakhalin\"
    },
    \"outside_hours_behavior\": \"bloqueia\",
    \"outside_hours_message\": \"w\",
    \"pre_chat_form\": \"exigido_para_enviar\",
    \"allowed_origins\": [
        \"a\"
    ]
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/widget-configs/564"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "appearance": {
        "primary_color": "#FeeEd",
        "logo_url": "http:\/\/www.dach.com\/mollitia-modi-deserunt-aut-ab-provident-perspiciatis-quo.html",
        "position": "bottom-left",
        "button_label": "m"
    },
    "initial_message": "y",
    "business_hours": {
        "monday": "25:59-31:42",
        "tuesday": "25:59-31:42",
        "wednesday": "25:59-31:42",
        "thursday": "25:59-31:42",
        "friday": "25:59-31:42",
        "saturday": "25:59-31:42",
        "sunday": "25:59-31:42",
        "timezone": "Asia\/Sakhalin"
    },
    "outside_hours_behavior": "bloqueia",
    "outside_hours_message": "w",
    "pre_chat_form": "exigido_para_enviar",
    "allowed_origins": [
        "a"
    ]
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-inbox-widget-configs--channelId-">
</span>
<span id="execution-results-PUTapi-v1-inbox-widget-configs--channelId-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-inbox-widget-configs--channelId-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-inbox-widget-configs--channelId-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-inbox-widget-configs--channelId-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-inbox-widget-configs--channelId-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-inbox-widget-configs--channelId-" data-method="PUT"
      data-path="api/v1/inbox/widget-configs/{channelId}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-inbox-widget-configs--channelId-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/inbox/widget-configs/{channelId}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>channelId</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="channelId"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="564"
               data-component="url">
    <br>
<p>Example: <code>564</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>appearance</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
<br>

            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>primary_color</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="appearance.primary_color"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="#FeeEd"
               data-component="body">
    <br>
<p>Must match the regex /^#[0-9a-fA-F]{3,6}$/. Example: <code>#FeeEd</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>logo_url</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="appearance.logo_url"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="http://www.dach.com/mollitia-modi-deserunt-aut-ab-provident-perspiciatis-quo.html"
               data-component="body">
    <br>
<p>Must be a valid URL. Must not be greater than 500 characters. Example: <code>http://www.dach.com/mollitia-modi-deserunt-aut-ab-provident-perspiciatis-quo.html</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>position</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="appearance.position"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="bottom-left"
               data-component="body">
    <br>
<p>Example: <code>bottom-left</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>bottom-right</code></li> <li><code>bottom-left</code></li></ul>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>button_label</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="appearance.button_label"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="m"
               data-component="body">
    <br>
<p>Must not be greater than 50 characters. Example: <code>m</code></p>
                    </div>
                                    </details>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>initial_message</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="initial_message"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="y"
               data-component="body">
    <br>
<p>Must not be greater than 500 characters. Example: <code>y</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>business_hours</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
<br>

            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>monday</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="business_hours.monday"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="25:59-31:42"
               data-component="body">
    <br>
<p>Must match the regex /^\d{2}:\d{2}-\d{2}:\d{2}$/. Example: <code>25:59-31:42</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>tuesday</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="business_hours.tuesday"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="25:59-31:42"
               data-component="body">
    <br>
<p>Must match the regex /^\d{2}:\d{2}-\d{2}:\d{2}$/. Example: <code>25:59-31:42</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>wednesday</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="business_hours.wednesday"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="25:59-31:42"
               data-component="body">
    <br>
<p>Must match the regex /^\d{2}:\d{2}-\d{2}:\d{2}$/. Example: <code>25:59-31:42</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>thursday</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="business_hours.thursday"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="25:59-31:42"
               data-component="body">
    <br>
<p>Must match the regex /^\d{2}:\d{2}-\d{2}:\d{2}$/. Example: <code>25:59-31:42</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>friday</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="business_hours.friday"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="25:59-31:42"
               data-component="body">
    <br>
<p>Must match the regex /^\d{2}:\d{2}-\d{2}:\d{2}$/. Example: <code>25:59-31:42</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>saturday</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="business_hours.saturday"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="25:59-31:42"
               data-component="body">
    <br>
<p>Must match the regex /^\d{2}:\d{2}-\d{2}:\d{2}$/. Example: <code>25:59-31:42</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>sunday</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="business_hours.sunday"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="25:59-31:42"
               data-component="body">
    <br>
<p>Must match the regex /^\d{2}:\d{2}-\d{2}:\d{2}$/. Example: <code>25:59-31:42</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>timezone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="business_hours.timezone"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="Asia/Sakhalin"
               data-component="body">
    <br>
<p>Must be a valid time zone, such as <code>Africa/Accra</code>. Example: <code>Asia/Sakhalin</code></p>
                    </div>
                                    </details>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>outside_hours_behavior</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="outside_hours_behavior"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="bloqueia"
               data-component="body">
    <br>
<p>Example: <code>bloqueia</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>bloqueia</code></li> <li><code>fila</code></li> <li><code>normal</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>outside_hours_message</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="outside_hours_message"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="w"
               data-component="body">
    <br>
<p>Must not be greater than 500 characters. Example: <code>w</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>pre_chat_form</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="pre_chat_form"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               value="exigido_para_enviar"
               data-component="body">
    <br>
<p>Example: <code>exigido_para_enviar</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>opcional</code></li> <li><code>exigido_para_iniciar</code></li> <li><code>exigido_para_enviar</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>allowed_origins</code></b>&nbsp;&nbsp;
<small>string[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="allowed_origins[0]"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               data-component="body">
        <input type="text" style="display: none"
               name="allowed_origins[1]"                data-endpoint="PUTapi-v1-inbox-widget-configs--channelId-"
               data-component="body">
    <br>
<p>Must be a valid URL. Must not be greater than 255 characters.</p>
        </div>
        </form>

                    <h2 id="endpoints-GETapi-v1-inbox-widget-configs--channelId--snippet">GET api/v1/inbox/widget-configs/{channelId}/snippet</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-inbox-widget-configs--channelId--snippet">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/widget-configs/564/snippet" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/widget-configs/564/snippet"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-inbox-widget-configs--channelId--snippet">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-inbox-widget-configs--channelId--snippet" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-inbox-widget-configs--channelId--snippet"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-inbox-widget-configs--channelId--snippet"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-inbox-widget-configs--channelId--snippet" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-inbox-widget-configs--channelId--snippet">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-inbox-widget-configs--channelId--snippet" data-method="GET"
      data-path="api/v1/inbox/widget-configs/{channelId}/snippet"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-inbox-widget-configs--channelId--snippet', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/inbox/widget-configs/{channelId}/snippet</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-inbox-widget-configs--channelId--snippet"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-inbox-widget-configs--channelId--snippet"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-inbox-widget-configs--channelId--snippet"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>channelId</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="channelId"                data-endpoint="GETapi-v1-inbox-widget-configs--channelId--snippet"
               value="564"
               data-component="url">
    <br>
<p>Example: <code>564</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-v1-inbox-conversations">GET /inbox/conversations
Lista conversas do tenant com filtros e aggregations.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-inbox-conversations">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"status\": [
        \"aberta\"
    ],
    \"channel_id\": 16,
    \"channel_type\": \"web\",
    \"assigned_user_id\": \"architecto\",
    \"patient_id\": 16,
    \"ai_paused\": \"true\",
    \"q\": \"ngzm\",
    \"per_page\": 15,
    \"page\": 43
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "status": [
        "aberta"
    ],
    "channel_id": 16,
    "channel_type": "web",
    "assigned_user_id": "architecto",
    "patient_id": 16,
    "ai_paused": "true",
    "q": "ngzm",
    "per_page": 15,
    "page": 43
};

fetch(url, {
    method: "GET",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-inbox-conversations">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-inbox-conversations" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-inbox-conversations"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-inbox-conversations"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-inbox-conversations" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-inbox-conversations">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-inbox-conversations" data-method="GET"
      data-path="api/v1/inbox/conversations"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-inbox-conversations', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/inbox/conversations</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-inbox-conversations"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-inbox-conversations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-inbox-conversations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status[0]"                data-endpoint="GETapi-v1-inbox-conversations"
               data-component="body">
        <input type="text" style="display: none"
               name="status[1]"                data-endpoint="GETapi-v1-inbox-conversations"
               data-component="body">
    <br>

Must be one of:
<ul style="list-style-type: square;"><li><code>aberta</code></li> <li><code>pendente</code></li> <li><code>resolvida</code></li> <li><code>reaberta</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>channel_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="channel_id"                data-endpoint="GETapi-v1-inbox-conversations"
               value="16"
               data-component="body">
    <br>
<p>Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>channel_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="channel_type"                data-endpoint="GETapi-v1-inbox-conversations"
               value="web"
               data-component="body">
    <br>
<p>Example: <code>web</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>whatsapp</code></li> <li><code>instagram</code></li> <li><code>web</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>assigned_user_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="assigned_user_id"                data-endpoint="GETapi-v1-inbox-conversations"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>patient_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="patient_id"                data-endpoint="GETapi-v1-inbox-conversations"
               value="16"
               data-component="body">
    <br>
<p>Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>ai_paused</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="ai_paused"                data-endpoint="GETapi-v1-inbox-conversations"
               value="true"
               data-component="body">
    <br>
<p>Example: <code>true</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>true</code></li> <li><code>false</code></li> <li><code>1</code></li> <li><code>0</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>last_activity_from</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="last_activity_from"                data-endpoint="GETapi-v1-inbox-conversations"
               value=""
               data-component="body">
    <br>

        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>last_activity_to</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="last_activity_to"                data-endpoint="GETapi-v1-inbox-conversations"
               value=""
               data-component="body">
    <br>

        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>q</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="q"                data-endpoint="GETapi-v1-inbox-conversations"
               value="ngzm"
               data-component="body">
    <br>
<p>Must be at least 2 characters. Example: <code>ngzm</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-v1-inbox-conversations"
               value="15"
               data-component="body">
    <br>
<p>Must be at least 1. Must not be greater than 100. Example: <code>15</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-v1-inbox-conversations"
               value="43"
               data-component="body">
    <br>
<p>Must be at least 1. Example: <code>43</code></p>
        </div>
        </form>

                    <h2 id="endpoints-GETapi-v1-inbox-conversations--id-">GET /inbox/conversations/{conversation}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Returns 404 (instead of 403) when the user is a medico who cannot see the conversation.
This hides existence of the record (privacy by design).</p>

<span id="example-requests-GETapi-v1-inbox-conversations--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-inbox-conversations--id-">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-inbox-conversations--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-inbox-conversations--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-inbox-conversations--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-inbox-conversations--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-inbox-conversations--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-inbox-conversations--id-" data-method="GET"
      data-path="api/v1/inbox/conversations/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-inbox-conversations--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/inbox/conversations/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-inbox-conversations--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-inbox-conversations--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-inbox-conversations--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="GETapi-v1-inbox-conversations--id-"
               value="16"
               data-component="url">
    <br>
<p>The ID of the conversation. Example: <code>16</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-v1-inbox-conversations--conversation_id--messages">GET /inbox/conversations/{conversation}/messages
Lista mensagens cursor-paginadas (DESC).</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-inbox-conversations--conversation_id--messages">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/messages" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/messages"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-inbox-conversations--conversation_id--messages">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-inbox-conversations--conversation_id--messages" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-inbox-conversations--conversation_id--messages"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-inbox-conversations--conversation_id--messages"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-inbox-conversations--conversation_id--messages" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-inbox-conversations--conversation_id--messages">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-inbox-conversations--conversation_id--messages" data-method="GET"
      data-path="api/v1/inbox/conversations/{conversation_id}/messages"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-inbox-conversations--conversation_id--messages', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/inbox/conversations/{conversation_id}/messages</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-inbox-conversations--conversation_id--messages"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-inbox-conversations--conversation_id--messages"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-inbox-conversations--conversation_id--messages"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>conversation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="conversation_id"                data-endpoint="GETapi-v1-inbox-conversations--conversation_id--messages"
               value="16"
               data-component="url">
    <br>
<p>The ID of the conversation. Example: <code>16</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-POSTapi-v1-inbox-conversations--conversation_id--messages">POST /inbox/conversations/{conversation}/messages
Envia mensagem outbound.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-inbox-conversations--conversation_id--messages">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/messages" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"idempotency_key\": \"bngzmiyvdljnikhw\",
    \"content_type\": \"text\",
    \"body\": \"a\",
    \"media_token\": \"architecto\",
    \"template\": {
        \"provider_template_id\": \"architecto\"
    }
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/messages"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "idempotency_key": "bngzmiyvdljnikhw",
    "content_type": "text",
    "body": "a",
    "media_token": "architecto",
    "template": {
        "provider_template_id": "architecto"
    }
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-inbox-conversations--conversation_id--messages">
</span>
<span id="execution-results-POSTapi-v1-inbox-conversations--conversation_id--messages" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-inbox-conversations--conversation_id--messages"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-inbox-conversations--conversation_id--messages"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-inbox-conversations--conversation_id--messages" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-inbox-conversations--conversation_id--messages">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-inbox-conversations--conversation_id--messages" data-method="POST"
      data-path="api/v1/inbox/conversations/{conversation_id}/messages"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-inbox-conversations--conversation_id--messages', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/inbox/conversations/{conversation_id}/messages</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--messages"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--messages"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--messages"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>conversation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="conversation_id"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--messages"
               value="16"
               data-component="url">
    <br>
<p>The ID of the conversation. Example: <code>16</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>idempotency_key</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="idempotency_key"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--messages"
               value="bngzmiyvdljnikhw"
               data-component="body">
    <br>
<p>Must be at least 1 character. Example: <code>bngzmiyvdljnikhw</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>content_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="content_type"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--messages"
               value="text"
               data-component="body">
    <br>
<p>Example: <code>text</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>text</code></li> <li><code>template</code></li> <li><code>image</code></li> <li><code>audio</code></li> <li><code>video</code></li> <li><code>document</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>body</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="body"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--messages"
               value="a"
               data-component="body">
    <br>
<p>Must not be greater than 4096 characters. Example: <code>a</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="media_token"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--messages"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>template</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
<br>

            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>provider_template_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="template.provider_template_id"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--messages"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>variables</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="template.variables"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--messages"
               value=""
               data-component="body">
    <br>

                    </div>
                                    </details>
        </div>
        </form>

                    <h2 id="endpoints-POSTapi-v1-inbox-conversations--conversation_id--read">POST /inbox/conversations/{conversation}/read</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-inbox-conversations--conversation_id--read">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/read" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/read"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-inbox-conversations--conversation_id--read">
</span>
<span id="execution-results-POSTapi-v1-inbox-conversations--conversation_id--read" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-inbox-conversations--conversation_id--read"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-inbox-conversations--conversation_id--read"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-inbox-conversations--conversation_id--read" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-inbox-conversations--conversation_id--read">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-inbox-conversations--conversation_id--read" data-method="POST"
      data-path="api/v1/inbox/conversations/{conversation_id}/read"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-inbox-conversations--conversation_id--read', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/inbox/conversations/{conversation_id}/read</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--read"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--read"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--read"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>conversation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="conversation_id"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--read"
               value="16"
               data-component="url">
    <br>
<p>The ID of the conversation. Example: <code>16</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-POSTapi-v1-inbox-conversations--conversation_id--resolve">POST /inbox/conversations/{conversation}/resolve</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-inbox-conversations--conversation_id--resolve">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/resolve" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/resolve"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-inbox-conversations--conversation_id--resolve">
</span>
<span id="execution-results-POSTapi-v1-inbox-conversations--conversation_id--resolve" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-inbox-conversations--conversation_id--resolve"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-inbox-conversations--conversation_id--resolve"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-inbox-conversations--conversation_id--resolve" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-inbox-conversations--conversation_id--resolve">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-inbox-conversations--conversation_id--resolve" data-method="POST"
      data-path="api/v1/inbox/conversations/{conversation_id}/resolve"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-inbox-conversations--conversation_id--resolve', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/inbox/conversations/{conversation_id}/resolve</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--resolve"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--resolve"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--resolve"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>conversation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="conversation_id"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--resolve"
               value="16"
               data-component="url">
    <br>
<p>The ID of the conversation. Example: <code>16</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-POSTapi-v1-inbox-conversations--conversation_id--reopen">POST /inbox/conversations/{conversation}/reopen</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-inbox-conversations--conversation_id--reopen">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/reopen" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/reopen"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-inbox-conversations--conversation_id--reopen">
</span>
<span id="execution-results-POSTapi-v1-inbox-conversations--conversation_id--reopen" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-inbox-conversations--conversation_id--reopen"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-inbox-conversations--conversation_id--reopen"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-inbox-conversations--conversation_id--reopen" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-inbox-conversations--conversation_id--reopen">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-inbox-conversations--conversation_id--reopen" data-method="POST"
      data-path="api/v1/inbox/conversations/{conversation_id}/reopen"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-inbox-conversations--conversation_id--reopen', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/inbox/conversations/{conversation_id}/reopen</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--reopen"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--reopen"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--reopen"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>conversation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="conversation_id"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--reopen"
               value="16"
               data-component="url">
    <br>
<p>The ID of the conversation. Example: <code>16</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-v1-inbox-poll">GET api/v1/inbox/poll</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-inbox-poll">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/poll" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/poll"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-inbox-poll">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-inbox-poll" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-inbox-poll"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-inbox-poll"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-inbox-poll" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-inbox-poll">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-inbox-poll" data-method="GET"
      data-path="api/v1/inbox/poll"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-inbox-poll', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/inbox/poll</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-inbox-poll"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-inbox-poll"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-inbox-poll"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-POSTapi-v1-inbox-conversations--conversation_id--takeover">POST /inbox/conversations/{conversation}/takeover</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Pausa a IA na conversa pelo tempo especificado (ou padrão do tenant).
Aceita:</p>
<ul>
<li>{} (padrão do config/tenant — clamped 5-240 min)</li>
<li>{"duration_hours": 4} (clamped 5-240 min)</li>
<li>{"until": "2026-05-12T18:00:00Z"} (timestamp explícito, sem clamp)</li>
</ul>

<span id="example-requests-POSTapi-v1-inbox-conversations--conversation_id--takeover">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/takeover" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"duration_hours\": 16,
    \"until\": \"2052-06-05\",
    \"reason\": \"n\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/takeover"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "duration_hours": 16,
    "until": "2052-06-05",
    "reason": "n"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-inbox-conversations--conversation_id--takeover">
</span>
<span id="execution-results-POSTapi-v1-inbox-conversations--conversation_id--takeover" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-inbox-conversations--conversation_id--takeover"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-inbox-conversations--conversation_id--takeover"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-inbox-conversations--conversation_id--takeover" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-inbox-conversations--conversation_id--takeover">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-inbox-conversations--conversation_id--takeover" data-method="POST"
      data-path="api/v1/inbox/conversations/{conversation_id}/takeover"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-inbox-conversations--conversation_id--takeover', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/inbox/conversations/{conversation_id}/takeover</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--takeover"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--takeover"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--takeover"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>conversation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="conversation_id"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--takeover"
               value="16"
               data-component="url">
    <br>
<p>The ID of the conversation. Example: <code>16</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>duration_hours</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="duration_hours"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--takeover"
               value="16"
               data-component="body">
    <br>
<p>Must be at least 1. Must not be greater than 24. Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>until</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="until"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--takeover"
               value="2052-06-05"
               data-component="body">
    <br>
<p>Must be a valid date in the format <code>Y-m-d\TH:i:sP</code>. Must be a date after <code>now</code>. Example: <code>2052-06-05</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>reason</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="reason"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--takeover"
               value="n"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>n</code></p>
        </div>
        </form>

                    <h2 id="endpoints-POSTapi-v1-inbox-conversations--conversation_id--release-to-ai">POST /inbox/conversations/{conversation}/release-to-ai</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Libera a IA imediatamente. Idempotente.</p>

<span id="example-requests-POSTapi-v1-inbox-conversations--conversation_id--release-to-ai">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/release-to-ai" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"duration_hours\": 16,
    \"until\": \"2052-06-05\",
    \"reason\": \"n\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/release-to-ai"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "duration_hours": 16,
    "until": "2052-06-05",
    "reason": "n"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-inbox-conversations--conversation_id--release-to-ai">
</span>
<span id="execution-results-POSTapi-v1-inbox-conversations--conversation_id--release-to-ai" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-inbox-conversations--conversation_id--release-to-ai"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-inbox-conversations--conversation_id--release-to-ai"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-inbox-conversations--conversation_id--release-to-ai" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-inbox-conversations--conversation_id--release-to-ai">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-inbox-conversations--conversation_id--release-to-ai" data-method="POST"
      data-path="api/v1/inbox/conversations/{conversation_id}/release-to-ai"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-inbox-conversations--conversation_id--release-to-ai', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/inbox/conversations/{conversation_id}/release-to-ai</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--release-to-ai"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--release-to-ai"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--release-to-ai"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>conversation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="conversation_id"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--release-to-ai"
               value="16"
               data-component="url">
    <br>
<p>The ID of the conversation. Example: <code>16</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>duration_hours</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="duration_hours"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--release-to-ai"
               value="16"
               data-component="body">
    <br>
<p>Must be at least 1. Must not be greater than 24. Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>until</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="until"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--release-to-ai"
               value="2052-06-05"
               data-component="body">
    <br>
<p>Must be a valid date in the format <code>Y-m-d\TH:i:sP</code>. Must be a date after <code>now</code>. Example: <code>2052-06-05</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>reason</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="reason"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--release-to-ai"
               value="n"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>n</code></p>
        </div>
        </form>

                    <h2 id="endpoints-POSTapi-v1-inbox-conversations--conversation_id--assign">POST /inbox/conversations/{conversation}/assign</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Atribuição manual (<code>user_id</code>) ou auto (<code>auto: true</code>).</p>

<span id="example-requests-POSTapi-v1-inbox-conversations--conversation_id--assign">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/assign" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"user_id\": 16,
    \"auto\": false,
    \"strategy\": \"patient_owner\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/assign"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "user_id": 16,
    "auto": false,
    "strategy": "patient_owner"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-inbox-conversations--conversation_id--assign">
</span>
<span id="execution-results-POSTapi-v1-inbox-conversations--conversation_id--assign" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-inbox-conversations--conversation_id--assign"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-inbox-conversations--conversation_id--assign"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-inbox-conversations--conversation_id--assign" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-inbox-conversations--conversation_id--assign">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-inbox-conversations--conversation_id--assign" data-method="POST"
      data-path="api/v1/inbox/conversations/{conversation_id}/assign"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-inbox-conversations--conversation_id--assign', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/inbox/conversations/{conversation_id}/assign</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--assign"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--assign"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--assign"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>conversation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="conversation_id"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--assign"
               value="16"
               data-component="url">
    <br>
<p>The ID of the conversation. Example: <code>16</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>user_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="user_id"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--assign"
               value="16"
               data-component="body">
    <br>
<p>Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>auto</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--assign" style="display: none">
            <input type="radio" name="auto"
                   value="true"
                   data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--assign"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--assign" style="display: none">
            <input type="radio" name="auto"
                   value="false"
                   data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--assign"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Example: <code>false</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>strategy</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="strategy"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--assign"
               value="patient_owner"
               data-component="body">
    <br>
<p>Example: <code>patient_owner</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>round_robin</code></li> <li><code>patient_owner</code></li></ul>
        </div>
        </form>

                    <h2 id="endpoints-POSTapi-v1-inbox-conversations--conversation_id--transfer">POST /inbox/conversations/{conversation}/transfer</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Transferência para usuário (<code>user_id</code>) ou role (<code>role</code>).</p>

<span id="example-requests-POSTapi-v1-inbox-conversations--conversation_id--transfer">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/transfer" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"user_id\": 16,
    \"role\": \"medico\",
    \"transfer_note\": \"n\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/transfer"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "user_id": 16,
    "role": "medico",
    "transfer_note": "n"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-inbox-conversations--conversation_id--transfer">
</span>
<span id="execution-results-POSTapi-v1-inbox-conversations--conversation_id--transfer" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-inbox-conversations--conversation_id--transfer"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-inbox-conversations--conversation_id--transfer"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-inbox-conversations--conversation_id--transfer" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-inbox-conversations--conversation_id--transfer">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-inbox-conversations--conversation_id--transfer" data-method="POST"
      data-path="api/v1/inbox/conversations/{conversation_id}/transfer"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-inbox-conversations--conversation_id--transfer', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/inbox/conversations/{conversation_id}/transfer</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--transfer"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--transfer"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--transfer"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>conversation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="conversation_id"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--transfer"
               value="16"
               data-component="url">
    <br>
<p>The ID of the conversation. Example: <code>16</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>user_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="user_id"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--transfer"
               value="16"
               data-component="body">
    <br>
<p>Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>role</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="role"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--transfer"
               value="medico"
               data-component="body">
    <br>
<p>Example: <code>medico</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>medico</code></li> <li><code>atendente</code></li> <li><code>recepcionista</code></li> <li><code>admin-clinica</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>transfer_note</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="transfer_note"                data-endpoint="POSTapi-v1-inbox-conversations--conversation_id--transfer"
               value="n"
               data-component="body">
    <br>
<p>Must be at least 10 characters. Must not be greater than 500 characters. Example: <code>n</code></p>
        </div>
        </form>

                    <h2 id="endpoints-GETapi-v1-inbox-conversations--conversation_id--assignments">GET /inbox/conversations/{conversation}/assignments</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Histórico cronológico de atribuições da conversa (DESC).</p>

<span id="example-requests-GETapi-v1-inbox-conversations--conversation_id--assignments">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/assignments" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/conversations/16/assignments"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-inbox-conversations--conversation_id--assignments">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-inbox-conversations--conversation_id--assignments" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-inbox-conversations--conversation_id--assignments"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-inbox-conversations--conversation_id--assignments"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-inbox-conversations--conversation_id--assignments" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-inbox-conversations--conversation_id--assignments">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-inbox-conversations--conversation_id--assignments" data-method="GET"
      data-path="api/v1/inbox/conversations/{conversation_id}/assignments"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-inbox-conversations--conversation_id--assignments', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/inbox/conversations/{conversation_id}/assignments</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-inbox-conversations--conversation_id--assignments"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-inbox-conversations--conversation_id--assignments"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-inbox-conversations--conversation_id--assignments"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>conversation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="conversation_id"                data-endpoint="GETapi-v1-inbox-conversations--conversation_id--assignments"
               value="16"
               data-component="url">
    <br>
<p>The ID of the conversation. Example: <code>16</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-v1-inbox-assignment-rules">GET /inbox/assignment-rules</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Lista regras ativas ordenadas por priority (asc = maior prioridade).</p>

<span id="example-requests-GETapi-v1-inbox-assignment-rules">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/assignment-rules" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/assignment-rules"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-inbox-assignment-rules">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-inbox-assignment-rules" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-inbox-assignment-rules"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-inbox-assignment-rules"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-inbox-assignment-rules" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-inbox-assignment-rules">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-inbox-assignment-rules" data-method="GET"
      data-path="api/v1/inbox/assignment-rules"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-inbox-assignment-rules', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/inbox/assignment-rules</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-inbox-assignment-rules"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-inbox-assignment-rules"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-inbox-assignment-rules"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-PUTapi-v1-inbox-assignment-rules">PUT /inbox/assignment-rules</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Substitui todas as regras do tenant em uma única transação.
Atomicamente: apaga as antigas, insere as novas.</p>

<span id="example-requests-PUTapi-v1-inbox-assignment-rules">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/assignment-rules" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"rules\": [
        {
            \"strategy\": \"patient_owner\",
            \"priority\": 16,
            \"is_active\": false,
            \"channel_id\": 16
        }
    ]
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/assignment-rules"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "rules": [
        {
            "strategy": "patient_owner",
            "priority": 16,
            "is_active": false,
            "channel_id": 16
        }
    ]
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-inbox-assignment-rules">
</span>
<span id="execution-results-PUTapi-v1-inbox-assignment-rules" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-inbox-assignment-rules"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-inbox-assignment-rules"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-inbox-assignment-rules" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-inbox-assignment-rules">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-inbox-assignment-rules" data-method="PUT"
      data-path="api/v1/inbox/assignment-rules"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-inbox-assignment-rules', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/inbox/assignment-rules</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-inbox-assignment-rules"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-inbox-assignment-rules"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-inbox-assignment-rules"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>rules</code></b>&nbsp;&nbsp;
<small>object[]</small>&nbsp;
 &nbsp;
 &nbsp;
<br>

            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>strategy</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="rules.0.strategy"                data-endpoint="PUTapi-v1-inbox-assignment-rules"
               value="patient_owner"
               data-component="body">
    <br>
<p>Example: <code>patient_owner</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>round_robin</code></li> <li><code>patient_owner</code></li> <li><code>manual</code></li></ul>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>priority</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="rules.0.priority"                data-endpoint="PUTapi-v1-inbox-assignment-rules"
               value="16"
               data-component="body">
    <br>
<p>Must be at least 1. Example: <code>16</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>is_active</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
 &nbsp;
 &nbsp;
                <label data-endpoint="PUTapi-v1-inbox-assignment-rules" style="display: none">
            <input type="radio" name="rules.0.is_active"
                   value="true"
                   data-endpoint="PUTapi-v1-inbox-assignment-rules"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PUTapi-v1-inbox-assignment-rules" style="display: none">
            <input type="radio" name="rules.0.is_active"
                   value="false"
                   data-endpoint="PUTapi-v1-inbox-assignment-rules"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Example: <code>false</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>config</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="rules.0.config"                data-endpoint="PUTapi-v1-inbox-assignment-rules"
               value=""
               data-component="body">
    <br>

                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>channel_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="rules.0.channel_id"                data-endpoint="PUTapi-v1-inbox-assignment-rules"
               value="16"
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the messaging_channels table. Example: <code>16</code></p>
                    </div>
                                    </details>
        </div>
        </form>

                    <h2 id="endpoints-GETapi-v1-inbox-quick-replies">GET /inbox/quick-replies</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Lista respostas rápidas visíveis para o usuário (tenant + próprias privadas).
Private overrides tenant quando shortcut coincide.</p>

<span id="example-requests-GETapi-v1-inbox-quick-replies">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/quick-replies" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/quick-replies"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-inbox-quick-replies">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-inbox-quick-replies" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-inbox-quick-replies"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-inbox-quick-replies"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-inbox-quick-replies" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-inbox-quick-replies">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-inbox-quick-replies" data-method="GET"
      data-path="api/v1/inbox/quick-replies"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-inbox-quick-replies', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/inbox/quick-replies</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-inbox-quick-replies"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-inbox-quick-replies"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-inbox-quick-replies"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-POSTapi-v1-inbox-quick-replies">POST /inbox/quick-replies</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Cria resposta rápida (scope=tenant exige quick_reply.manage;
scope=private qualquer inbox.respond).</p>

<span id="example-requests-POSTapi-v1-inbox-quick-replies">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/quick-replies" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"scope\": \"private\",
    \"shortcut\": \"b\",
    \"content\": \"n\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/quick-replies"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "scope": "private",
    "shortcut": "b",
    "content": "n"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-inbox-quick-replies">
</span>
<span id="execution-results-POSTapi-v1-inbox-quick-replies" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-inbox-quick-replies"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-inbox-quick-replies"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-inbox-quick-replies" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-inbox-quick-replies">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-inbox-quick-replies" data-method="POST"
      data-path="api/v1/inbox/quick-replies"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-inbox-quick-replies', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/inbox/quick-replies</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-inbox-quick-replies"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-inbox-quick-replies"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-inbox-quick-replies"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>scope</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="scope"                data-endpoint="POSTapi-v1-inbox-quick-replies"
               value="private"
               data-component="body">
    <br>
<p>Example: <code>private</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>tenant</code></li> <li><code>private</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>shortcut</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="shortcut"                data-endpoint="POSTapi-v1-inbox-quick-replies"
               value="b"
               data-component="body">
    <br>
<p>Must match the regex /^\/[a-zA-Z0-9_-]+$/. Must be at least 2 characters. Must not be greater than 50 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>content</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="content"                data-endpoint="POSTapi-v1-inbox-quick-replies"
               value="n"
               data-component="body">
    <br>
<p>Must be at least 1 character. Must not be greater than 4096 characters. Example: <code>n</code></p>
        </div>
        </form>

                    <h2 id="endpoints-PUTapi-v1-inbox-quick-replies--id-">PATCH /inbox/quick-replies/{quickReply}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Atualiza resposta rápida (policy: tenant-scope exige quick_reply.manage; privada = owner).</p>

<span id="example-requests-PUTapi-v1-inbox-quick-replies--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/quick-replies/16" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"shortcut\": \"b\",
    \"content\": \"n\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/quick-replies/16"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "shortcut": "b",
    "content": "n"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-inbox-quick-replies--id-">
</span>
<span id="execution-results-PUTapi-v1-inbox-quick-replies--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-inbox-quick-replies--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-inbox-quick-replies--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-inbox-quick-replies--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-inbox-quick-replies--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-inbox-quick-replies--id-" data-method="PUT"
      data-path="api/v1/inbox/quick-replies/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-inbox-quick-replies--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/inbox/quick-replies/{id}</code></b>
        </p>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/inbox/quick-replies/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-inbox-quick-replies--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-inbox-quick-replies--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-inbox-quick-replies--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="PUTapi-v1-inbox-quick-replies--id-"
               value="16"
               data-component="url">
    <br>
<p>The ID of the quick reply. Example: <code>16</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>shortcut</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="shortcut"                data-endpoint="PUTapi-v1-inbox-quick-replies--id-"
               value="b"
               data-component="body">
    <br>
<p>Must match the regex /^\/[a-zA-Z0-9_-]+$/. Must be at least 2 characters. Must not be greater than 50 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>content</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="content"                data-endpoint="PUTapi-v1-inbox-quick-replies--id-"
               value="n"
               data-component="body">
    <br>
<p>Must be at least 1 character. Must not be greater than 4096 characters. Example: <code>n</code></p>
        </div>
        </form>

                    <h2 id="endpoints-DELETEapi-v1-inbox-quick-replies--id-">DELETE /inbox/quick-replies/{quickReply}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Remove resposta rápida (policy: tenant-scope exige quick_reply.manage; privada = owner).</p>

<span id="example-requests-DELETEapi-v1-inbox-quick-replies--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/quick-replies/16" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/quick-replies/16"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-inbox-quick-replies--id-">
</span>
<span id="execution-results-DELETEapi-v1-inbox-quick-replies--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-inbox-quick-replies--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-inbox-quick-replies--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-inbox-quick-replies--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-inbox-quick-replies--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-inbox-quick-replies--id-" data-method="DELETE"
      data-path="api/v1/inbox/quick-replies/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-inbox-quick-replies--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/inbox/quick-replies/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-inbox-quick-replies--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-inbox-quick-replies--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-inbox-quick-replies--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="DELETEapi-v1-inbox-quick-replies--id-"
               value="16"
               data-component="url">
    <br>
<p>The ID of the quick reply. Example: <code>16</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-POSTapi-v1-inbox-quick-replies--quickReply_id--render">POST /inbox/quick-replies/{quickReply}/render</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Renderiza variáveis em contexto de uma conversa para preview server-side.</p>

<span id="example-requests-POSTapi-v1-inbox-quick-replies--quickReply_id--render">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/quick-replies/16/render" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"conversation_id\": 16
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/quick-replies/16/render"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "conversation_id": 16
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-inbox-quick-replies--quickReply_id--render">
</span>
<span id="execution-results-POSTapi-v1-inbox-quick-replies--quickReply_id--render" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-inbox-quick-replies--quickReply_id--render"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-inbox-quick-replies--quickReply_id--render"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-inbox-quick-replies--quickReply_id--render" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-inbox-quick-replies--quickReply_id--render">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-inbox-quick-replies--quickReply_id--render" data-method="POST"
      data-path="api/v1/inbox/quick-replies/{quickReply_id}/render"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-inbox-quick-replies--quickReply_id--render', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/inbox/quick-replies/{quickReply_id}/render</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-inbox-quick-replies--quickReply_id--render"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-inbox-quick-replies--quickReply_id--render"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-inbox-quick-replies--quickReply_id--render"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>quickReply_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="quickReply_id"                data-endpoint="POSTapi-v1-inbox-quick-replies--quickReply_id--render"
               value="16"
               data-component="url">
    <br>
<p>The ID of the quickReply. Example: <code>16</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>conversation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="conversation_id"                data-endpoint="POSTapi-v1-inbox-quick-replies--quickReply_id--render"
               value="16"
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the messaging_conversations table. Example: <code>16</code></p>
        </div>
        </form>

                    <h2 id="endpoints-POSTapi-v1-inbox-media-upload">POST /api/v1/inbox/media/upload</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Solicita URL pré-assinada PUT para upload direto ao S3.
Retorna <code>media_token</code> que deve ser incluído no payload da mensagem.</p>

<span id="example-requests-POSTapi-v1-inbox-media-upload">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/media/upload" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"conversation_id\": 16,
    \"mime_type\": \"video\\/mp4\",
    \"size_bytes\": 22,
    \"original_filename\": \"g\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/media/upload"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "conversation_id": 16,
    "mime_type": "video\/mp4",
    "size_bytes": 22,
    "original_filename": "g"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-inbox-media-upload">
</span>
<span id="execution-results-POSTapi-v1-inbox-media-upload" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-inbox-media-upload"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-inbox-media-upload"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-inbox-media-upload" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-inbox-media-upload">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-inbox-media-upload" data-method="POST"
      data-path="api/v1/inbox/media/upload"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-inbox-media-upload', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/inbox/media/upload</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-inbox-media-upload"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-inbox-media-upload"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-inbox-media-upload"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>conversation_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="conversation_id"                data-endpoint="POSTapi-v1-inbox-media-upload"
               value="16"
               data-component="body">
    <br>
<p>Must be at least 1. Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>mime_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="mime_type"                data-endpoint="POSTapi-v1-inbox-media-upload"
               value="video/mp4"
               data-component="body">
    <br>
<p>Example: <code>video/mp4</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>image/jpeg</code></li> <li><code>image/png</code></li> <li><code>image/webp</code></li> <li><code>audio/mpeg</code></li> <li><code>audio/ogg</code></li> <li><code>audio/mp4</code></li> <li><code>video/mp4</code></li> <li><code>application/pdf</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>size_bytes</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="size_bytes"                data-endpoint="POSTapi-v1-inbox-media-upload"
               value="22"
               data-component="body">
    <br>
<p>Must be at least 1. Example: <code>22</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>original_filename</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="original_filename"                data-endpoint="POSTapi-v1-inbox-media-upload"
               value="g"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>g</code></p>
        </div>
        </form>

                    <h2 id="endpoints-GETapi-v1-inbox-media--id-">GET /api/v1/inbox/media/{id}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retorna URL de download assinada (24h) ou 410 se mídia foi purgada.</p>

<span id="example-requests-GETapi-v1-inbox-media--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/media/564" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/media/564"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-inbox-media--id-">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-inbox-media--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-inbox-media--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-inbox-media--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-inbox-media--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-inbox-media--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-inbox-media--id-" data-method="GET"
      data-path="api/v1/inbox/media/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-inbox-media--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/inbox/media/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-inbox-media--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-inbox-media--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-inbox-media--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-v1-inbox-media--id-"
               value="564"
               data-component="url">
    <br>
<p>The ID of the medium. Example: <code>564</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-POSTapi-v1-inbox-presence-heartbeat">POST /api/v1/inbox/presence/heartbeat</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Registra heartbeat — atualiza <code>last_seen_at</code> para agora.
Cria linha se não existir. Retorna 204 No Content.</p>

<span id="example-requests-POSTapi-v1-inbox-presence-heartbeat">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/presence/heartbeat" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/presence/heartbeat"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-inbox-presence-heartbeat">
</span>
<span id="execution-results-POSTapi-v1-inbox-presence-heartbeat" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-inbox-presence-heartbeat"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-inbox-presence-heartbeat"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-inbox-presence-heartbeat" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-inbox-presence-heartbeat">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-inbox-presence-heartbeat" data-method="POST"
      data-path="api/v1/inbox/presence/heartbeat"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-inbox-presence-heartbeat', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/inbox/presence/heartbeat</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-inbox-presence-heartbeat"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-inbox-presence-heartbeat"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-inbox-presence-heartbeat"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-GETapi-v1-inbox-presence">GET /api/v1/inbox/presence</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Lista atendentes do tenant com status inferido (online/offline).</p>

<span id="example-requests-GETapi-v1-inbox-presence">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/presence" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/presence"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-inbox-presence">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-inbox-presence" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-inbox-presence"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-inbox-presence"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-inbox-presence" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-inbox-presence">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-inbox-presence" data-method="GET"
      data-path="api/v1/inbox/presence"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-inbox-presence', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/inbox/presence</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-inbox-presence"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-inbox-presence"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-inbox-presence"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-PATCHapi-v1-inbox-presence-me">PATCH /api/v1/inbox/presence/me</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Atualiza preferências do atendente atual.</p>

<span id="example-requests-PATCHapi-v1-inbox-presence-me">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/presence/me" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"max_concurrent_conversations\": 1,
    \"notification_preferences\": {
        \"new_conversation\": false,
        \"new_message\": false,
        \"assignment\": false
    }
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/inbox/presence/me"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "max_concurrent_conversations": 1,
    "notification_preferences": {
        "new_conversation": false,
        "new_message": false,
        "assignment": false
    }
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PATCHapi-v1-inbox-presence-me">
</span>
<span id="execution-results-PATCHapi-v1-inbox-presence-me" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PATCHapi-v1-inbox-presence-me"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-v1-inbox-presence-me"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-v1-inbox-presence-me" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-v1-inbox-presence-me">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PATCHapi-v1-inbox-presence-me" data-method="PATCH"
      data-path="api/v1/inbox/presence/me"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-v1-inbox-presence-me', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/inbox/presence/me</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-v1-inbox-presence-me"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-v1-inbox-presence-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-v1-inbox-presence-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>max_concurrent_conversations</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="max_concurrent_conversations"                data-endpoint="PATCHapi-v1-inbox-presence-me"
               value="1"
               data-component="body">
    <br>
<p>Must be at least 1. Must not be greater than 50. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>notification_preferences</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
<br>

            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>new_conversation</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PATCHapi-v1-inbox-presence-me" style="display: none">
            <input type="radio" name="notification_preferences.new_conversation"
                   value="true"
                   data-endpoint="PATCHapi-v1-inbox-presence-me"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PATCHapi-v1-inbox-presence-me" style="display: none">
            <input type="radio" name="notification_preferences.new_conversation"
                   value="false"
                   data-endpoint="PATCHapi-v1-inbox-presence-me"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Example: <code>false</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>new_message</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PATCHapi-v1-inbox-presence-me" style="display: none">
            <input type="radio" name="notification_preferences.new_message"
                   value="true"
                   data-endpoint="PATCHapi-v1-inbox-presence-me"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PATCHapi-v1-inbox-presence-me" style="display: none">
            <input type="radio" name="notification_preferences.new_message"
                   value="false"
                   data-endpoint="PATCHapi-v1-inbox-presence-me"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Example: <code>false</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>assignment</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PATCHapi-v1-inbox-presence-me" style="display: none">
            <input type="radio" name="notification_preferences.assignment"
                   value="true"
                   data-endpoint="PATCHapi-v1-inbox-presence-me"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PATCHapi-v1-inbox-presence-me" style="display: none">
            <input type="radio" name="notification_preferences.assignment"
                   value="false"
                   data-endpoint="PATCHapi-v1-inbox-presence-me"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Example: <code>false</code></p>
                    </div>
                                    </details>
        </div>
        </form>

                    <h2 id="endpoints-POSTapi-v1-webhooks-twilio-whatsapp">POST api/v1/webhooks/twilio/whatsapp</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-webhooks-twilio-whatsapp">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/webhooks/twilio/whatsapp" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/webhooks/twilio/whatsapp"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-webhooks-twilio-whatsapp">
</span>
<span id="execution-results-POSTapi-v1-webhooks-twilio-whatsapp" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-webhooks-twilio-whatsapp"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-webhooks-twilio-whatsapp"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-webhooks-twilio-whatsapp" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-webhooks-twilio-whatsapp">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-webhooks-twilio-whatsapp" data-method="POST"
      data-path="api/v1/webhooks/twilio/whatsapp"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-webhooks-twilio-whatsapp', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/webhooks/twilio/whatsapp</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-webhooks-twilio-whatsapp"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-webhooks-twilio-whatsapp"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-webhooks-twilio-whatsapp"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-POSTapi-v1-webhooks-twilio-status">POST api/v1/webhooks/twilio/status</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-webhooks-twilio-status">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/webhooks/twilio/status" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/webhooks/twilio/status"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-webhooks-twilio-status">
</span>
<span id="execution-results-POSTapi-v1-webhooks-twilio-status" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-webhooks-twilio-status"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-webhooks-twilio-status"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-webhooks-twilio-status" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-webhooks-twilio-status">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-webhooks-twilio-status" data-method="POST"
      data-path="api/v1/webhooks/twilio/status"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-webhooks-twilio-status', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/webhooks/twilio/status</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-webhooks-twilio-status"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-webhooks-twilio-status"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-webhooks-twilio-status"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-GETapi-v1-webhooks-instagram">GET handshake — Meta confirma a URL do webhook antes de entregar eventos.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Meta envia: hub.mode=subscribe, hub.challenge=<token>, hub.verify_token=<secret>
Deve responder: 200 com o challenge em PLAIN TEXT (não JSON).</p>
<p>Atenção: PHP converte pontos em parâmetros de query para underscores.
hub.mode → hub_mode, hub.verify_token → hub_verify_token, etc.</p>

<span id="example-requests-GETapi-v1-webhooks-instagram">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/webhooks/instagram" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/webhooks/instagram"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-webhooks-instagram">
            <blockquote>
            <p>Example response (403):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
strict-transport-security: max-age=31536000; includeSubDomains; preload
x-frame-options: DENY
x-content-type-options: nosniff
referrer-policy: strict-origin-when-cross-origin
content-security-policy: default-src &#039;self&#039; &#039;unsafe-inline&#039; &#039;unsafe-eval&#039;; img-src &#039;self&#039; data: https:; connect-src &#039;self&#039; ws: wss:; frame-ancestors &#039;none&#039;; base-uri &#039;self&#039;
x-request-id: 01KRGJAJR0VXHE3WAP216CFMH1
x-ratelimit-limit: 1000
x-ratelimit-remaining: 999
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">forbidden</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-webhooks-instagram" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-webhooks-instagram"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-webhooks-instagram"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-webhooks-instagram" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-webhooks-instagram">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-webhooks-instagram" data-method="GET"
      data-path="api/v1/webhooks/instagram"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-webhooks-instagram', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/webhooks/instagram</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-webhooks-instagram"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-webhooks-instagram"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-webhooks-instagram"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-POSTapi-v1-webhooks-instagram">POST inbound — processa DMs Instagram entregues pela Meta.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>O payload pode conter múltiplos entry[] e cada entry múltiplos messaging[].
Cada evento é gravado de forma idempotente pelo WebhookEventRecorder e
um job assíncrono é despachado para processar a mensagem.</p>
<p>Sempre responde 200 para evitar retry imediato da Meta.</p>
<p>T270 — métricas Prometheus registradas após processamento:</p>
<ul>
<li>webhookReceived(provider, status)</li>
<li>webhookProcessingDuration(provider, seconds)</li>
</ul>

<span id="example-requests-POSTapi-v1-webhooks-instagram">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/webhooks/instagram" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/webhooks/instagram"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-webhooks-instagram">
</span>
<span id="execution-results-POSTapi-v1-webhooks-instagram" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-webhooks-instagram"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-webhooks-instagram"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-webhooks-instagram" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-webhooks-instagram">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-webhooks-instagram" data-method="POST"
      data-path="api/v1/webhooks/instagram"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-webhooks-instagram', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/webhooks/instagram</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-webhooks-instagram"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-webhooks-instagram"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-webhooks-instagram"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                <h1 id="onboarding">Onboarding</h1>

    

                                <h2 id="onboarding-GETapi-v1-onboarding-state">Estado atual do wizard de onboarding.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retorna progresso e status de cada etapa para o tenant atual.</p>

<span id="example-requests-GETapi-v1-onboarding-state">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/onboarding/state" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/onboarding/state"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-onboarding-state">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-onboarding-state" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-onboarding-state"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-onboarding-state"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-onboarding-state" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-onboarding-state">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-onboarding-state" data-method="GET"
      data-path="api/v1/onboarding/state"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-onboarding-state', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/onboarding/state</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-onboarding-state"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-onboarding-state"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-onboarding-state"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="onboarding-POSTapi-v1-onboarding-steps--stepKey--complete">Marcar etapa como concluída.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>O payload varia por etapa (stepKey). Etapa fora desta fase retorna 409.</p>

<span id="example-requests-POSTapi-v1-onboarding-steps--stepKey--complete">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/onboarding/steps/architecto/complete" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/onboarding/steps/architecto/complete"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-onboarding-steps--stepKey--complete">
</span>
<span id="execution-results-POSTapi-v1-onboarding-steps--stepKey--complete" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-onboarding-steps--stepKey--complete"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-onboarding-steps--stepKey--complete"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-onboarding-steps--stepKey--complete" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-onboarding-steps--stepKey--complete">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-onboarding-steps--stepKey--complete" data-method="POST"
      data-path="api/v1/onboarding/steps/{stepKey}/complete"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-onboarding-steps--stepKey--complete', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/onboarding/steps/{stepKey}/complete</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-onboarding-steps--stepKey--complete"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-onboarding-steps--stepKey--complete"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-onboarding-steps--stepKey--complete"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>stepKey</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="stepKey"                data-endpoint="POSTapi-v1-onboarding-steps--stepKey--complete"
               value="architecto"
               data-component="url">
    <br>
<p>Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="onboarding-POSTapi-v1-onboarding-steps--stepKey--skip">Pular etapa não-bloqueante do wizard.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Etapas bloqueantes (required=true) não podem ser puladas e retornam 409.</p>

<span id="example-requests-POSTapi-v1-onboarding-steps--stepKey--skip">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/onboarding/steps/architecto/skip" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/onboarding/steps/architecto/skip"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-onboarding-steps--stepKey--skip">
</span>
<span id="execution-results-POSTapi-v1-onboarding-steps--stepKey--skip" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-onboarding-steps--stepKey--skip"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-onboarding-steps--stepKey--skip"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-onboarding-steps--stepKey--skip" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-onboarding-steps--stepKey--skip">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-onboarding-steps--stepKey--skip" data-method="POST"
      data-path="api/v1/onboarding/steps/{stepKey}/skip"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-onboarding-steps--stepKey--skip', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/onboarding/steps/{stepKey}/skip</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-onboarding-steps--stepKey--skip"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-onboarding-steps--stepKey--skip"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-onboarding-steps--stepKey--skip"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>stepKey</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="stepKey"                data-endpoint="POSTapi-v1-onboarding-steps--stepKey--skip"
               value="architecto"
               data-component="url">
    <br>
<p>Example: <code>architecto</code></p>
            </div>
                    </form>

                <h1 id="tenant">Tenant</h1>

    

                                <h2 id="tenant-POSTapi-v1-tenants-register">Cadastro público de nova clínica (tenant).</h2>

<p>
</p>

<p>Cria o tenant em estado <code>trial</code>, o usuário criador com perfil
Admin Clínica e envia e-mail de boas-vindas.</p>

<span id="example-requests-POSTapi-v1-tenants-register">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/tenants/register" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"clinic_name\": \"b\",
    \"cnpj\": \"ngzmiyvdljnikh\",
    \"slug\": \"w\",
    \"responsible_name\": \"a\",
    \"responsible_email\": \"breitenberg.gilbert@example.com\",
    \"responsible_phone\": \"uwpwlvqwrsitcpsc\",
    \"password\": \":x&amp;S$hS\",
    \"terms_accepted\": true,
    \"terms_version\": \"tujwvlxjklqppwqb\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/tenants/register"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "clinic_name": "b",
    "cnpj": "ngzmiyvdljnikh",
    "slug": "w",
    "responsible_name": "a",
    "responsible_email": "breitenberg.gilbert@example.com",
    "responsible_phone": "uwpwlvqwrsitcpsc",
    "password": ":x&amp;S$hS",
    "terms_accepted": true,
    "terms_version": "tujwvlxjklqppwqb"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-tenants-register">
            <blockquote>
            <p>Example response (201, tenant criado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;tenant&quot;: {
        &quot;id&quot;: 1,
        &quot;slug&quot;: &quot;clinica-alfa&quot;
    },
    &quot;login_url&quot;: &quot;https://clinica-alfa.crm.com.br/login&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-tenants-register" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-tenants-register"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-tenants-register"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-tenants-register" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-tenants-register">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-tenants-register" data-method="POST"
      data-path="api/v1/tenants/register"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-tenants-register', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/tenants/register</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-tenants-register"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-tenants-register"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>clinic_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="clinic_name"                data-endpoint="POSTapi-v1-tenants-register"
               value="b"
               data-component="body">
    <br>
<p>Must be at least 3 characters. Must not be greater than 150 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>cnpj</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="cnpj"                data-endpoint="POSTapi-v1-tenants-register"
               value="ngzmiyvdljnikh"
               data-component="body">
    <br>
<p>Must match the regex /^\d{14}$/. Must be 14 characters. Example: <code>ngzmiyvdljnikh</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>slug</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="slug"                data-endpoint="POSTapi-v1-tenants-register"
               value="w"
               data-component="body">
    <br>
<p>Must match the regex /^<a href="[a-z0-9-]{1,61}[a-z0-9]">a-z</a>?$/. Must be at least 3 characters. Must not be greater than 63 characters. Example: <code>w</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>responsible_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="responsible_name"                data-endpoint="POSTapi-v1-tenants-register"
               value="a"
               data-component="body">
    <br>
<p>Must be at least 3 characters. Must not be greater than 150 characters. Example: <code>a</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>responsible_email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="responsible_email"                data-endpoint="POSTapi-v1-tenants-register"
               value="breitenberg.gilbert@example.com"
               data-component="body">
    <br>
<p>Must be a valid email address. Must not be greater than 254 characters. Example: <code>breitenberg.gilbert@example.com</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>responsible_phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="responsible_phone"                data-endpoint="POSTapi-v1-tenants-register"
               value="uwpwlvqwrsitcpsc"
               data-component="body">
    <br>
<p>Must be at least 8 characters. Must not be greater than 20 characters. Example: <code>uwpwlvqwrsitcpsc</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-v1-tenants-register"
               value=":x&S$hS"
               data-component="body">
    <br>
<p>Must match the regex /[A-Z]/. Must match the regex /\d/. Must be at least 8 characters. Example: <code>:x&amp;S$hS</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>terms_accepted</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
 &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-v1-tenants-register" style="display: none">
            <input type="radio" name="terms_accepted"
                   value="true"
                   data-endpoint="POSTapi-v1-tenants-register"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-v1-tenants-register" style="display: none">
            <input type="radio" name="terms_accepted"
                   value="false"
                   data-endpoint="POSTapi-v1-tenants-register"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Must be accepted. Example: <code>true</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>terms_version</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="terms_version"                data-endpoint="POSTapi-v1-tenants-register"
               value="tujwvlxjklqppwqb"
               data-component="body">
    <br>
<p>Must not be greater than 20 characters. Example: <code>tujwvlxjklqppwqb</code></p>
        </div>
        </form>

                    <h2 id="tenant-GETapi-v1-tenant">Leitura do tenant atual.</h2>

<p>
</p>

<p>Retorna dados públicos do tenant ativo (branding, status de trial,
plano vigente). Usado pelo SPA na inicialização.</p>

<span id="example-requests-GETapi-v1-tenant">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/tenant" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/tenant"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-tenant">
            <blockquote>
            <p>Example response (200, tenant ativo):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: 1,
    &quot;slug&quot;: &quot;clinica-alfa&quot;,
    &quot;status&quot;: &quot;trial&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, subdomínio inválido):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Tenant n&atilde;o encontrado.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-tenant" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-tenant"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-tenant"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-tenant" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-tenant">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-tenant" data-method="GET"
      data-path="api/v1/tenant"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-tenant', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/tenant</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-tenant"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-tenant"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                <h1 id="users">Users</h1>

    

                                <h2 id="users-GETapi-v1-users">Listar usuários internos do tenant.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Suporta filtros por <code>status</code> (invited|active|disabled) e <code>role</code>.</p>

<span id="example-requests-GETapi-v1-users">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/users" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/users"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-users">
            <blockquote>
            <p>Example response (200, lista):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 1,
            &quot;name&quot;: &quot;Dr. Exemplo&quot;,
            &quot;status&quot;: &quot;active&quot;
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-users" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-users"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-users"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-users" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-users">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-users" data-method="GET"
      data-path="api/v1/users"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-users', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/users</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-users"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-users"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-users"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="users-PATCHapi-v1-users--id-">Alterar perfil ou roles do usuário.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PATCHapi-v1-users--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/users/architecto" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"b\",
    \"roles\": [
        \"recepcionista\"
    ]
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/users/architecto"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "b",
    "roles": [
        "recepcionista"
    ]
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PATCHapi-v1-users--id-">
            <blockquote>
            <p>Example response (200, atualizado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: 1,
    &quot;name&quot;: &quot;Dr. Novo Nome&quot;,
    &quot;roles&quot;: [
        &quot;medico&quot;
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-PATCHapi-v1-users--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PATCHapi-v1-users--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-v1-users--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-v1-users--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-v1-users--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PATCHapi-v1-users--id-" data-method="PATCH"
      data-path="api/v1/users/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-v1-users--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/users/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-v1-users--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-v1-users--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-v1-users--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PATCHapi-v1-users--id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the user. Example: <code>architecto</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="PATCHapi-v1-users--id-"
               value="b"
               data-component="body">
    <br>
<p>Must not be greater than 150 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>roles</code></b>&nbsp;&nbsp;
<small>string[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="roles[0]"                data-endpoint="PATCHapi-v1-users--id-"
               data-component="body">
        <input type="text" style="display: none"
               name="roles[1]"                data-endpoint="PATCHapi-v1-users--id-"
               data-component="body">
    <br>

Must be one of:
<ul style="list-style-type: square;"><li><code>admin-clinica</code></li> <li><code>medico</code></li> <li><code>atendente</code></li> <li><code>recepcionista</code></li> <li><code>financeiro</code></li></ul>
        </div>
        </form>

                    <h2 id="users-DELETEapi-v1-users--id-">Desativar usuário (soft-delete; preserva auditoria).</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retorna 409 se for o último Admin Clínica do tenant.</p>

<span id="example-requests-DELETEapi-v1-users--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/users/architecto" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/users/architecto"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-users--id-">
            <blockquote>
            <p>Example response (204, desativado):</p>
        </blockquote>
                <pre>
<code>Empty response</code>
 </pre>
            <blockquote>
            <p>Example response (409, último admin):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;N&atilde;o &eacute; poss&iacute;vel desativar o &uacute;ltimo Admin Cl&iacute;nica.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-v1-users--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-users--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-users--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-users--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-users--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-users--id-" data-method="DELETE"
      data-path="api/v1/users/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-users--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/users/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-users--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-users--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-users--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-v1-users--id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the user. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="users-GETapi-v1-users-invitations">Listar convites pendentes.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retorna apenas convites com <code>status=pending</code> do tenant atual.</p>

<span id="example-requests-GETapi-v1-users-invitations">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://9392-177-18-76-77.ngrok-free.app/api/v1/users/invitations" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/users/invitations"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-users-invitations">
            <blockquote>
            <p>Example response (200, convites pendentes):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">[
    {
        &quot;id&quot;: 1,
        &quot;email&quot;: &quot;novo@clinica.com.br&quot;,
        &quot;intended_role&quot;: &quot;medico&quot;
    }
]</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-users-invitations" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-users-invitations"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-users-invitations"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-users-invitations" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-users-invitations">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-users-invitations" data-method="GET"
      data-path="api/v1/users/invitations"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-users-invitations', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/users/invitations</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-users-invitations"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-users-invitations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-users-invitations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="users-POSTapi-v1-users-invitations">Enviar convite de novo usuário interno.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Dispara e-mail ao convidado. Retorna 409 se o limite de usuários do
plano foi atingido.</p>

<span id="example-requests-POSTapi-v1-users-invitations">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/users/invitations" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"gbailey@example.net\",
    \"intended_role\": \"recepcionista\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/users/invitations"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "email": "gbailey@example.net",
    "intended_role": "recepcionista"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-users-invitations">
            <blockquote>
            <p>Example response (201, convite enviado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: 1,
    &quot;email&quot;: &quot;novo@clinica.com.br&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (409, limite atingido):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Limite de usu&aacute;rios do plano atingido.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-users-invitations" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-users-invitations"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-users-invitations"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-users-invitations" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-users-invitations">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-users-invitations" data-method="POST"
      data-path="api/v1/users/invitations"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-users-invitations', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/users/invitations</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-users-invitations"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-users-invitations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-users-invitations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-users-invitations"
               value="gbailey@example.net"
               data-component="body">
    <br>
<p>Must be a valid email address. Must not be greater than 254 characters. Example: <code>gbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>intended_role</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="intended_role"                data-endpoint="POSTapi-v1-users-invitations"
               value="recepcionista"
               data-component="body">
    <br>
<p>Example: <code>recepcionista</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>admin-clinica</code></li> <li><code>medico</code></li> <li><code>atendente</code></li> <li><code>recepcionista</code></li> <li><code>financeiro</code></li></ul>
        </div>
        </form>

                    <h2 id="users-DELETEapi-v1-users-invitations--id-">Revogar convite pendente.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retorna 410 se o convite já foi aceito ou expirou.</p>

<span id="example-requests-DELETEapi-v1-users-invitations--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/users/invitations/architecto" \
    --header "Authorization: Bearer paciente360_&amp;lt;seu-token&amp;gt;" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/users/invitations/architecto"
);

const headers = {
    "Authorization": "Bearer paciente360_&amp;lt;seu-token&amp;gt;",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-users-invitations--id-">
            <blockquote>
            <p>Example response (204, revogado):</p>
        </blockquote>
                <pre>
<code>Empty response</code>
 </pre>
            <blockquote>
            <p>Example response (410, convite expirado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Convite j&aacute; aceito ou expirado.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-v1-users-invitations--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-users-invitations--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-users-invitations--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-users-invitations--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-users-invitations--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-users-invitations--id-" data-method="DELETE"
      data-path="api/v1/users/invitations/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-users-invitations--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/users/invitations/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-users-invitations--id-"
               value="Bearer paciente360_<seu-token>"
               data-component="header">
    <br>
<p>Example: <code>Bearer paciente360_&lt;seu-token&gt;</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-users-invitations--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-users-invitations--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-v1-users-invitations--id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the invitation. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="users-POSTapi-v1-users-invitations-accept">Aceitar convite e definir senha.</h2>

<p>
</p>

<p>Endpoint público (token vem no e-mail). O tenant é resolvido pelo
subdomínio. Token de outro tenant retorna 410.</p>

<span id="example-requests-POSTapi-v1-users-invitations-accept">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/users/invitations/accept" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"token\": \"bngzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtnn\",
    \"name\": \"o\",
    \"password\": \"u.*,JHRp_B)L\'(?aiG;o\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://9392-177-18-76-77.ngrok-free.app/api/v1/users/invitations/accept"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "token": "bngzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtnn",
    "name": "o",
    "password": "u.*,JHRp_B)L'(?aiG;o"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-users-invitations-accept">
            <blockquote>
            <p>Example response (200, usuário ativado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: 1,
    &quot;name&quot;: &quot;Dr. Novo&quot;,
    &quot;email&quot;: &quot;novo@clinica.com.br&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (410, token inválido):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Token expirado, inv&aacute;lido ou de outro tenant.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-users-invitations-accept" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-users-invitations-accept"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-users-invitations-accept"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-users-invitations-accept" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-users-invitations-accept">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-users-invitations-accept" data-method="POST"
      data-path="api/v1/users/invitations/accept"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-users-invitations-accept', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/users/invitations/accept</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-users-invitations-accept"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-users-invitations-accept"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="token"                data-endpoint="POSTapi-v1-users-invitations-accept"
               value="bngzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtnn"
               data-component="body">
    <br>
<p>Must be at least 32 characters. Example: <code>bngzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtnn</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-v1-users-invitations-accept"
               value="o"
               data-component="body">
    <br>
<p>Must not be greater than 150 characters. Example: <code>o</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-v1-users-invitations-accept"
               value="u.*,JHRp_B)L'(?aiG;o"
               data-component="body">
    <br>
<p>Must match the regex /[A-Z]/. Must match the regex /[0-9]/. Must be at least 8 characters. Example: <code>u.*,JHRp_B)L'(?aiG;o</code></p>
        </div>
        </form>

            

        
    </div>
    <div class="dark-box">
                    <div class="lang-selector">
                                                        <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                                        <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                            </div>
            </div>
</div>
</body>
</html>
