/**
 * T219 — Entry point do widget de chat web embutível.
 *
 * Fluxo de boot:
 *  1. Extrai a public_key da URL do próprio script (`src` attribute).
 *  2. Faz GET /widget/v1/{publicKey}/config para buscar aparência + Reverb creds.
 *  3. Recupera ou cria sessão de visitante (POST /widget/v1/{publicKey}/sessions).
 *  4. Monta UI (WidgetUI) e conecta transporte (Transport — Reverb ou SSE fallback).
 *  5. Opcional: exibe formulário pré-chat conforme configuração.
 *
 * Bundle target: IIFE, ~30 KB gzip, sem dependências externas além de pusher-js
 * (incluído no bundle via rollup external se disponível no window).
 *
 * Instalação no site cliente:
 *   <script src="https://widget.seudominio.com.br/widget/v1/{publicKey}.js" async></script>
 */

import { WidgetUI } from './ui.js';
import { Transport } from './transport.js';
import { getVisitorToken, saveVisitorToken, generateIdempotencyKey } from './auth.js';

const BASE_URL = self.location.origin; // fallback — sobrescrito pela public_key detection

/**
 * Extrai a public_key do atributo `src` do próprio script.
 *
 * Espera URL no formato: /widget/v1/{publicKey}.js
 *
 * @returns {string|null}
 */
function extractPublicKey() {
  const scripts = document.querySelectorAll('script[src]');
  for (const script of scripts) {
    const match = script.src.match(/\/widget\/v1\/([a-f0-9]{64})\.js/);
    if (match) {
      return match[1];
    }
  }
  // Fallback: busca no script atual via document.currentScript
  if (document.currentScript) {
    const match = (document.currentScript.src || '').match(/\/widget\/v1\/([a-f0-9]{64})\.js/);
    if (match) {
      return match[1];
    }
  }
  return null;
}

/**
 * Determina o base URL a partir da src do script (ex: https://widget.crm.com.br).
 *
 * @returns {string}
 */
function extractBaseUrl() {
  const scripts = document.querySelectorAll('script[src]');
  for (const script of scripts) {
    if (/\/widget\/v1\/[a-f0-9]{64}\.js/.test(script.src)) {
      const url = new URL(script.src);
      return `${url.protocol}//${url.host}`;
    }
  }
  if (document.currentScript?.src) {
    const url = new URL(document.currentScript.src);
    return `${url.protocol}//${url.host}`;
  }
  return window.location.origin;
}

/**
 * Função principal de boot — chamada automaticamente ao carregar o script.
 */
async function boot() {
  const publicKey = extractPublicKey();
  if (!publicKey) {
    console.warn('[P360 Widget] public_key não encontrada na URL do script.');
    return;
  }

  const baseUrl = extractBaseUrl();
  const apiBase = `${baseUrl}/widget/v1/${publicKey}`;

  // 1. Fetch config
  let config;
  try {
    const res = await fetch(`${apiBase}/config`, {
      headers: { Accept: 'application/json' },
    });
    if (!res.ok) {
      throw new Error(`Config fetch failed: ${res.status}`);
    }
    config = await res.json();
  } catch (err) {
    console.warn('[P360 Widget] Falha ao buscar configuração:', err);
    return;
  }

  // 2. Start/recover session
  const savedToken = getVisitorToken();
  let visitorToken;
  let sessionId;

  try {
    const sessionPayload = savedToken ? { visitor_token: savedToken } : {};
    const res = await fetch(`${apiBase}/sessions`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(sessionPayload),
    });
    if (!res.ok) {
      // Session start failure (e.g. pre-chat required) — handled later by UI
      const data = await res.json().catch(() => ({}));
      if (data.error === 'pre_chat_required') {
        // Continue boot — UI will show pre-chat form
        visitorToken = null;
      } else {
        throw new Error(`Session start failed: ${res.status}`);
      }
    } else {
      const data = await res.json();
      visitorToken = data.visitor_token;
      sessionId = data.session_id;
      saveVisitorToken(visitorToken);
    }
  } catch (err) {
    console.warn('[P360 Widget] Falha ao iniciar sessão:', err);
    return;
  }

  // 3. Mount UI
  const appearance = config.appearance || {};
  const preChatMode = config.pre_chat_form || 'opcional';

  let transport = null;

  const ui = new WidgetUI({
    appearance,
    config,
    onSendMessage: async (text) => {
      if (!visitorToken) {
        throw new Error('no_session');
      }
      const idempotencyKey = generateIdempotencyKey();
      const res = await fetch(`${apiBase}/messages`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Visitor-Token': visitorToken,
          'Idempotency-Key': idempotencyKey,
        },
        body: JSON.stringify({ content_type: 'text', body: text }),
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.error || `Send failed: ${res.status}`);
      }
    },
    onPreChatSubmit: async (preChatData) => {
      // Re-start session with pre-chat data
      const res = await fetch(`${apiBase}/sessions`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ visitor_token: savedToken, pre_chat_data: preChatData }),
      });
      if (!res.ok) {
        throw new Error('pre_chat_submit_failed');
      }
      const data = await res.json();
      visitorToken = data.visitor_token;
      sessionId = data.session_id;
      saveVisitorToken(visitorToken);
      ui.hidePrechatForm();

      // Start transport after session established
      _startTransport();
    },
    onReady: () => {
      // If session established: start transport
      if (visitorToken) {
        _startTransport();
      } else if (preChatMode === 'exigido_para_iniciar' || preChatMode === 'exigido_para_enviar') {
        // Show pre-chat form first
        ui.showPreChatForm(async (data) => {
          await ui._opts.onPreChatSubmit(data);
        });
      }
    },
  });

  function _startTransport() {
    if (!visitorToken || transport) {
      return;
    }

    const reverb = config.reverb || {};
    transport = new Transport({
      appKey: reverb.app_key || '',
      host: reverb.host || 'localhost',
      port: reverb.port || 8080,
      scheme: reverb.scheme || 'http',
      channelName: `widget.session.${visitorToken}`,
      sseUrl: `${apiBase}/messages/stream`,
      onMessage: (msg) => {
        // Only render messages from the other side (direction=in, sender_type=user|system|ai)
        if (msg && (msg.direction === 'in' || msg.sender_type !== 'patient')) {
          ui.appendMessage(msg);
        }
      },
      onStatus: (status) => {
        ui.setStatus(status);
      },
    });
    transport.connect();
  }

  ui.mount();
}

// Auto-boot when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
