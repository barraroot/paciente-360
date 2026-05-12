/**
 * T220 — UI do widget: DOM vanilla, zero dependências externas.
 *
 * Cria e controla:
 *  - Botão flutuante (launcher)
 *  - Painel de chat (header + timeline + input)
 *  - Formulário pré-chat
 *  - Banner de fora do horário
 *  - Indicador de status de conexão
 *
 * Namespace CSS: todas as classes prefixadas com `p360w-` para não colidir
 * com o CSS do site hospedeiro.
 *
 * @param {object} opts
 * @param {object} opts.appearance   — { primary_color, position, logo_url, welcome_message }
 * @param {object} opts.config       — resposta completa da API /config
 * @param {Function} opts.onSendMessage  — (text: string) => Promise<void>
 * @param {Function} opts.onPreChatSubmit — (data: object) => Promise<void>
 * @param {Function} opts.onReady    — chamado após UI montada
 */
export class WidgetUI {
  constructor(opts) {
    this._opts = opts;
    this._isOpen = false;
    this._messages = [];
    this._status = 'connecting';
    this._outsideHours = false;
    this._behavior = opts.config.outside_hours_behavior || 'normal';
    this._outsideHoursMsg = opts.config.outside_hours_message || 'Estamos fechados no momento.';
    this._preChatMode = opts.config.pre_chat_form || 'opcional';
    this._preChatDone = false;

    this._root = null;
    this._panel = null;
    this._timeline = null;
    this._input = null;
    this._sendBtn = null;
    this._statusBar = null;
    this._launcher = null;
  }

  mount() {
    this._injectStyles();
    this._buildLauncher();
    this._buildPanel();
    document.body.appendChild(this._root);
    this._opts.onReady();
  }

  // ─── Public API ──────────────────────────────────────────────────────────

  appendMessage(msg) {
    this._messages.push(msg);
    this._renderMessage(msg);
    this._scrollBottom();
  }

  setStatus(status) {
    this._status = status;
    if (this._statusBar) {
      this._statusBar.textContent = this._statusLabel(status);
      this._statusBar.className = `p360w-status p360w-status--${status}`;
    }
  }

  setOutsideHours(outside) {
    this._outsideHours = outside;
    this._updateInputState();
    if (outside && this._behavior !== 'normal') {
      this._showOutsideHoursBanner();
    }
  }

  showPreChatForm(onSubmit) {
    this._panel.querySelector('.p360w-pre-chat')?.remove();

    const form = this._createEl('form', 'p360w-pre-chat');
    form.innerHTML = this._preChatFormHtml();

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      const nome = (fd.get('nome') || '').trim();
      if (!nome) {
        form.querySelector('.p360w-error').textContent = 'Por favor, informe seu nome.';
        return;
      }
      form.querySelector('.p360w-error').textContent = '';
      onSubmit({ nome, telefone: fd.get('telefone') || '', email: fd.get('email') || '' });
      form.remove();
      this._preChatDone = true;
      this._updateInputState();
    });

    this._panel.querySelector('.p360w-body').prepend(form);
  }

  hidePrechatForm() {
    this._panel.querySelector('.p360w-pre-chat')?.remove();
    this._preChatDone = true;
    this._updateInputState();
  }

  // ─── Private ─────────────────────────────────────────────────────────────

  _primaryColor() {
    return (this._opts.appearance || {}).primary_color || '#0F59A0';
  }

  _position() {
    return (this._opts.appearance || {}).position || 'bottom-right';
  }

  _statusLabel(status) {
    const labels = {
      connecting: 'Conectando...',
      connected: '',
      disconnected: 'Desconectado. Tentando reconectar...',
      sse: 'Conectado (modo compatibilidade)',
    };
    return labels[status] || '';
  }

  _buildLauncher() {
    const pos = this._position();
    const color = this._primaryColor();

    this._root = this._createEl('div', 'p360w-root');
    this._root.style.cssText = `position:fixed;z-index:2147483647;${this._positionCss(pos)}`;

    this._launcher = this._createEl('button', 'p360w-launcher');
    this._launcher.setAttribute('aria-label', 'Abrir chat');
    this._launcher.style.cssText = `background:${color};width:56px;height:56px;border-radius:50%;border:none;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,0.25);display:flex;align-items:center;justify-content:center;transition:transform .2s;`;
    this._launcher.innerHTML = this._chatIcon();
    this._launcher.addEventListener('click', () => this._toggle());

    this._root.appendChild(this._launcher);
  }

  _buildPanel() {
    const color = this._primaryColor();

    this._panel = this._createEl('div', 'p360w-panel');
    this._panel.setAttribute('aria-label', 'Chat');
    this._panel.style.cssText = `display:none;flex-direction:column;width:360px;max-height:520px;border-radius:12px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-family:system-ui,-apple-system,sans-serif;font-size:14px;`;

    // Header
    const header = this._createEl('div', 'p360w-header');
    header.style.cssText = `background:${color};color:#fff;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;`;

    const logoUrl = (this._opts.appearance || {}).logo_url;
    const titleHtml = logoUrl
      ? `<img src="${logoUrl}" alt="Logo" style="height:28px;max-width:120px;object-fit:contain;">`
      : `<span style="font-weight:600;font-size:15px;">Fale Conosco</span>`;

    header.innerHTML = `${titleHtml}<button class="p360w-close-btn" aria-label="Fechar" style="background:none;border:none;color:#fff;cursor:pointer;font-size:20px;line-height:1;padding:0 4px;">×</button>`;
    header.querySelector('.p360w-close-btn').addEventListener('click', () => this._toggle());

    // Status bar
    this._statusBar = this._createEl('div', 'p360w-status');
    this._statusBar.style.cssText = `background:#fff3cd;color:#856404;font-size:11px;padding:4px 12px;text-align:center;`;

    // Body / timeline
    const body = this._createEl('div', 'p360w-body');
    body.style.cssText = `flex:1;overflow-y:auto;padding:12px;background:#f8f9fa;display:flex;flex-direction:column;gap:8px;`;
    this._timeline = body;

    // Welcome
    const welcome = (this._opts.appearance || {}).welcome_message || (this._opts.config.initial_message || '');
    if (welcome) {
      const wEl = this._createEl('div', 'p360w-msg p360w-msg--in');
      wEl.style.cssText = `align-self:flex-start;background:#fff;border-radius:8px;padding:8px 12px;max-width:80%;box-shadow:0 1px 3px rgba(0,0,0,.1);`;
      wEl.textContent = welcome;
      this._timeline.appendChild(wEl);
    }

    // Input area
    const footer = this._createEl('div', 'p360w-footer');
    footer.style.cssText = `background:#fff;border-top:1px solid #e9ecef;padding:8px 12px;display:flex;gap:8px;align-items:flex-end;`;

    this._input = this._createEl('textarea', 'p360w-input');
    this._input.setAttribute('placeholder', 'Digite sua mensagem...');
    this._input.setAttribute('rows', '1');
    this._input.style.cssText = `flex:1;resize:none;border:1px solid #dee2e6;border-radius:8px;padding:8px;font-size:14px;font-family:inherit;outline:none;max-height:120px;overflow-y:auto;`;
    this._input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        this._handleSend();
      }
    });

    this._sendBtn = this._createEl('button', 'p360w-send');
    this._sendBtn.style.cssText = `background:${color};color:#fff;border:none;border-radius:8px;padding:8px 14px;cursor:pointer;font-size:13px;font-weight:600;white-space:nowrap;`;
    this._sendBtn.textContent = 'Enviar';
    this._sendBtn.addEventListener('click', () => this._handleSend());

    footer.appendChild(this._input);
    footer.appendChild(this._sendBtn);

    this._panel.appendChild(header);
    this._panel.appendChild(this._statusBar);
    this._panel.appendChild(body);
    this._panel.appendChild(footer);

    this._root.insertBefore(this._panel, this._launcher);
    this._updateInputState();
  }

  _toggle() {
    this._isOpen = !this._isOpen;
    this._panel.style.display = this._isOpen ? 'flex' : 'none';
    this._launcher.style.transform = this._isOpen ? 'scale(0.9)' : 'scale(1)';
    if (this._isOpen) {
      this._input?.focus();
      this._scrollBottom();
    }
  }

  async _handleSend() {
    const text = (this._input.value || '').trim();
    if (!text) {
      return;
    }
    this._input.value = '';
    this._input.disabled = true;
    this._sendBtn.disabled = true;

    // Optimistic UI
    this._renderMessage({ body: text, direction: 'out', sender_type: 'patient' });
    this._scrollBottom();

    try {
      await this._opts.onSendMessage(text);
    } catch {
      // Show error as system message
      this._renderMessage({ body: 'Erro ao enviar mensagem. Tente novamente.', direction: 'in', sender_type: 'system' });
    } finally {
      this._input.disabled = false;
      this._sendBtn.disabled = false;
      this._input.focus();
    }
  }

  _renderMessage(msg) {
    const isOut = msg.direction === 'out' || msg.sender_type === 'patient';
    const el = this._createEl('div', `p360w-msg p360w-msg--${isOut ? 'out' : 'in'}`);
    el.textContent = msg.body || '';
    el.style.cssText = isOut
      ? `align-self:flex-end;background:${this._primaryColor()};color:#fff;border-radius:8px;padding:8px 12px;max-width:80%;`
      : `align-self:flex-start;background:#fff;border-radius:8px;padding:8px 12px;max-width:80%;box-shadow:0 1px 3px rgba(0,0,0,.1);`;
    this._timeline.appendChild(el);
  }

  _scrollBottom() {
    if (this._timeline) {
      this._timeline.scrollTop = this._timeline.scrollHeight;
    }
  }

  _updateInputState() {
    const blocked = this._outsideHours && this._behavior === 'bloqueia';
    const needsPreChat =
      !this._preChatDone && (this._preChatMode === 'exigido_para_enviar' || this._preChatMode === 'exigido_para_iniciar');

    if (this._input) {
      this._input.disabled = blocked || needsPreChat;
    }
    if (this._sendBtn) {
      this._sendBtn.disabled = blocked || needsPreChat;
    }
  }

  _showOutsideHoursBanner() {
    const existing = this._panel?.querySelector('.p360w-outside-hours');
    if (existing) {
      return;
    }
    const banner = this._createEl('div', 'p360w-outside-hours');
    banner.style.cssText = `background:#fff3cd;color:#856404;padding:8px 12px;font-size:12px;text-align:center;`;
    banner.textContent = this._outsideHoursMsg;
    this._timeline?.parentElement?.insertBefore(banner, this._timeline);
  }

  _preChatFormHtml() {
    return `
      <div style="background:#fff;border-radius:8px;padding:16px;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,.1);">
        <h3 style="margin:0 0 12px;font-size:14px;font-weight:600;">Antes de começar</h3>
        <label style="display:block;margin-bottom:8px;font-size:12px;color:#495057;">
          Nome <span style="color:red">*</span>
          <input name="nome" type="text" placeholder="Seu nome" required
            style="display:block;width:100%;margin-top:4px;padding:6px 8px;border:1px solid #dee2e6;border-radius:6px;font-size:13px;box-sizing:border-box;">
        </label>
        <label style="display:block;margin-bottom:8px;font-size:12px;color:#495057;">
          Telefone
          <input name="telefone" type="tel" placeholder="+55 11 9 9999-9999"
            style="display:block;width:100%;margin-top:4px;padding:6px 8px;border:1px solid #dee2e6;border-radius:6px;font-size:13px;box-sizing:border-box;">
        </label>
        <label style="display:block;margin-bottom:12px;font-size:12px;color:#495057;">
          E-mail
          <input name="email" type="email" placeholder="voce@exemplo.com"
            style="display:block;width:100%;margin-top:4px;padding:6px 8px;border:1px solid #dee2e6;border-radius:6px;font-size:13px;box-sizing:border-box;">
        </label>
        <span class="p360w-error" style="color:red;font-size:11px;display:block;margin-bottom:8px;"></span>
        <button type="submit" style="background:${this._primaryColor()};color:#fff;border:none;border-radius:6px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;width:100%;">
          Iniciar conversa
        </button>
      </div>`;
  }

  _positionCss(pos) {
    const map = {
      'bottom-right': 'bottom:24px;right:24px;',
      'bottom-left': 'bottom:24px;left:24px;',
      'top-right': 'top:24px;right:24px;',
      'top-left': 'top:24px;left:24px;',
    };
    return map[pos] || map['bottom-right'];
  }

  _chatIcon() {
    return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
    </svg>`;
  }

  _createEl(tag, className) {
    const el = document.createElement(tag);
    if (className) {
      el.className = className;
    }
    return el;
  }

  _injectStyles() {
    if (document.getElementById('p360w-styles')) {
      return;
    }
    const style = document.createElement('style');
    style.id = 'p360w-styles';
    style.textContent = `
      .p360w-status:empty { display: none !important; }
      .p360w-input:focus { border-color: #86b7fe; box-shadow: 0 0 0 2px rgba(13,110,253,.15); }
      .p360w-launcher:hover { transform: scale(1.08) !important; }
      .p360w-send:hover { opacity: .9; }
      @media (max-width: 420px) {
        .p360w-panel { width: 100vw !important; border-radius: 0 !important; max-height: 100dvh !important; }
      }
    `;
    document.head.appendChild(style);
  }
}
