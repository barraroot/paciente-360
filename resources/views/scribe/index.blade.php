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
                                <a href="#auth-POSTapi-v1-auth-login">Login de usuário interno.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-logout">
                                <a href="#auth-POSTapi-v1-auth-logout">Encerrar sessão atual.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-GETapi-v1-auth-me">
                                <a href="#auth-GETapi-v1-auth-me">Usuário autenticado e permissões efetivas.</a>
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
        <li>Last updated: May 10, 2026</li>
    </ul>
</div>

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        <h1 id="introduction">Introduction</h1>
<p>API REST do Paciente360 — CRM médico SaaS multi-tenant. Todos os endpoints autenticados são escopados ao tenant resolvido pelo subdomínio.</p>
<aside>
    <strong>Base URL</strong>: <code>http://localhost</code>
</aside>
<pre><code>This documentation aims to provide all the information you need to work with our API.

&lt;aside&gt;As you scroll, you'll see code examples for working with the API in different programming languages in the dark area to the right (or as part of the content on mobile).
You can switch the language used with the tabs at the top right (or from the nav menu at the top left on mobile).&lt;/aside&gt;</code></pre>

        <h1 id="authenticating-requests">Authenticating requests</h1>
<p>To authenticate requests, include a <strong><code>X-XSRF-TOKEN</code></strong> header with the value <strong><code>"{YOUR_AUTH_KEY}"</code></strong>.</p>
<p>All authenticated endpoints are marked with a <code>requires authentication</code> badge in the documentation below.</p>
<p>You can retrieve your token by visiting your dashboard and clicking <b>Generate API token</b>.</p>

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
    --get "http://localhost/api/v1/audit-logs/export" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/audit-logs/export"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="GETapi-v1-audit-logs-export"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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
    --get "http://localhost/api/v1/audit-logs" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/audit-logs"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="GETapi-v1-audit-logs"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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

    

                                <h2 id="auth-POSTapi-v1-auth-login">Login de usuário interno.</h2>

<p>
</p>

<p>Estabelece sessão Sanctum (cookie HttpOnly + CSRF). O tenant é
resolvido pelo subdomínio antes deste controller executar.</p>

<span id="example-requests-POSTapi-v1-auth-login">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost/api/v1/auth/login" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"gbailey@example.net\",
    \"password\": \"|]|{+-\",
    \"remember\": true
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/auth/login"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "email": "gbailey@example.net",
    "password": "|]|{+-",
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
            <p>Example response (200, sucesso):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;user&quot;: {
        &quot;id&quot;: 1,
        &quot;name&quot;: &quot;Dr. Exemplo&quot;,
        &quot;email&quot;: &quot;dr@clinica.com.br&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (401, credenciais inválidas):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Credenciais inv&aacute;lidas.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (423, bloqueado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Conta bloqueada temporariamente.&quot;
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
<p>Must be a valid email address. Example: <code>gbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-v1-auth-login"
               value="|]|{+-"
               data-component="body">
    <br>
<p>Example: <code>|]|{+-</code></p>
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

                    <h2 id="auth-POSTapi-v1-auth-logout">Encerrar sessão atual.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Invalida a sessão Sanctum e regenera o CSRF token.</p>

<span id="example-requests-POSTapi-v1-auth-logout">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost/api/v1/auth/logout" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/auth/logout"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="POSTapi-v1-auth-logout"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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

                    <h2 id="auth-GETapi-v1-auth-me">Usuário autenticado e permissões efetivas.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retorna o perfil do usuário logado com todas as permissões calculadas
para o tenant atual (Spatie team mode).</p>

<span id="example-requests-GETapi-v1-auth-me">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost/api/v1/auth/me" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/auth/me"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
    &quot;id&quot;: 1,
    &quot;name&quot;: &quot;Dr. Exemplo&quot;,
    &quot;email&quot;: &quot;dr@clinica.com.br&quot;,
    &quot;permissions&quot;: [
        &quot;users.invite&quot;
    ]
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="GETapi-v1-auth-me"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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

                    <h2 id="auth-POSTapi-v1-auth-password-forgot">Solicitar link de recuperação de senha.</h2>

<p>
</p>

<p>Resposta sempre 202, mesmo se o e-mail não existir (FR-032 — anti-enumeração).</p>

<span id="example-requests-POSTapi-v1-auth-password-forgot">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost/api/v1/auth/password/forgot" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"gbailey@example.net\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/auth/password/forgot"
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
    "http://localhost/api/v1/auth/password/reset" \
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
    "http://localhost/api/v1/auth/password/reset"
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
    --get "http://localhost/api/v1/billing/plans" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/billing/plans"
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
    "http://localhost/api/v1/billing/checkout" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"plan_code\": \"architecto\",
    \"professionals_quantity\": 22
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/billing/checkout"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="POSTapi-v1-billing-checkout"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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
    --get "http://localhost/api/v1/billing/subscription" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/billing/subscription"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="GETapi-v1-billing-subscription"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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
    "http://localhost/api/v1/billing/subscription" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
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
    "http://localhost/api/v1/billing/subscription"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="PATCHapi-v1-billing-subscription"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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
    --get "http://localhost/api/v1/billing/ai-usage" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/billing/ai-usage"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="GETapi-v1-billing-ai-usage"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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
    "http://localhost/api/v1/billing/ai-usage/hard-cap" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"hard_cap\": 27
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/billing/ai-usage/hard-cap"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="PATCHapi-v1-billing-ai-usage-hard-cap"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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
    --get "http://localhost/api/v1/onboarding/state" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/onboarding/state"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="GETapi-v1-onboarding-state"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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
    "http://localhost/api/v1/onboarding/steps/architecto/complete" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/onboarding/steps/architecto/complete"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="POSTapi-v1-onboarding-steps--stepKey--complete"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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
    "http://localhost/api/v1/onboarding/steps/architecto/skip" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/onboarding/steps/architecto/skip"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="POSTapi-v1-onboarding-steps--stepKey--skip"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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
    "http://localhost/api/v1/tenants/register" \
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
    "http://localhost/api/v1/tenants/register"
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
    --get "http://localhost/api/v1/tenant" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/tenant"
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
    --get "http://localhost/api/v1/users" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/users"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="GETapi-v1-users"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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
    "http://localhost/api/v1/users/architecto" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
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
    "http://localhost/api/v1/users/architecto"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="PATCHapi-v1-users--id-"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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
    "http://localhost/api/v1/users/architecto" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/users/architecto"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="DELETEapi-v1-users--id-"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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
    --get "http://localhost/api/v1/users/invitations" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/users/invitations"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="GETapi-v1-users-invitations"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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
    "http://localhost/api/v1/users/invitations" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"gbailey@example.net\",
    \"intended_role\": \"atendente\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/users/invitations"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "email": "gbailey@example.net",
    "intended_role": "atendente"
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="POSTapi-v1-users-invitations"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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
               value="atendente"
               data-component="body">
    <br>
<p>Example: <code>atendente</code></p>
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
    "http://localhost/api/v1/users/invitations/architecto" \
    --header "X-XSRF-TOKEN: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost/api/v1/users/invitations/architecto"
);

const headers = {
    "X-XSRF-TOKEN": "{YOUR_AUTH_KEY}",
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
                <b style="line-height: 2;"><code>X-XSRF-TOKEN</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-XSRF-TOKEN" class="auth-value"               data-endpoint="DELETEapi-v1-users-invitations--id-"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
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
    "http://localhost/api/v1/users/invitations/accept" \
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
    "http://localhost/api/v1/users/invitations/accept"
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
