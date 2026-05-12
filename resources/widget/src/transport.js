/**
 * T221 — Camada de transporte: Reverb WebSocket + SSE fallback.
 *
 * Estratégia:
 *  1. Tenta Reverb via pusher-js (WebSocket).
 *  2. Se falhar após MAX_WS_RETRIES, cai para SSE (polling passivo com heartbeat).
 *
 * onMessage(msg) — callback chamado com { type, body, sender_type, created_at }
 * onStatus(status) — callback: 'connecting'|'connected'|'disconnected'|'sse'
 */

const MAX_WS_RETRIES = 3;

export class Transport {
  /**
   * @param {object} opts
   * @param {string} opts.appKey      — Reverb app key
   * @param {string} opts.host        — Reverb host
   * @param {number} opts.port        — Reverb port
   * @param {string} opts.scheme      — 'http' | 'https'
   * @param {string} opts.channelName — ex: 'widget.session.{visitor_token}'
   * @param {string} opts.sseUrl      — URL do SSE fallback
   * @param {Function} opts.onMessage
   * @param {Function} opts.onStatus
   */
  constructor(opts) {
    this._opts = opts;
    this._echo = null;
    this._sseSource = null;
    this._wsRetries = 0;
    this._destroyed = false;
  }

  connect() {
    this._opts.onStatus('connecting');
    this._tryReverb();
  }

  _tryReverb() {
    // pusher-js is loaded as external — injected by widget.js boot after pusher-js CDN/bundle
    if (typeof window.Pusher === 'undefined') {
      this._fallbackToSse();
      return;
    }

    try {
      const pusher = new window.Pusher(this._opts.appKey, {
        wsHost: this._opts.host,
        wsPort: this._opts.port,
        wssPort: this._opts.port,
        forceTLS: this._opts.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        cluster: '',
        disableStats: true,
      });

      pusher.connection.bind('connected', () => {
        this._wsRetries = 0;
        this._opts.onStatus('connected');
      });

      pusher.connection.bind('error', () => {
        this._wsRetries++;
        if (this._wsRetries >= MAX_WS_RETRIES) {
          pusher.disconnect();
          this._fallbackToSse();
        }
      });

      pusher.connection.bind('disconnected', () => {
        if (!this._destroyed) {
          this._opts.onStatus('disconnected');
        }
      });

      const channel = pusher.subscribe(this._opts.channelName);
      channel.bind('message.new', (data) => {
        this._opts.onMessage(data);
      });

      this._echo = pusher;
    } catch {
      this._fallbackToSse();
    }
  }

  _fallbackToSse() {
    if (this._destroyed) {
      return;
    }
    this._opts.onStatus('sse');

    try {
      this._sseSource = new EventSource(this._opts.sseUrl);

      this._sseSource.addEventListener('message', (e) => {
        try {
          const data = JSON.parse(e.data);
          if (data.type === 'message.new') {
            this._opts.onMessage(data.payload);
          }
        } catch {
          // malformed SSE event — ignore
        }
      });

      this._sseSource.onerror = () => {
        this._opts.onStatus('disconnected');
      };
    } catch {
      this._opts.onStatus('disconnected');
    }
  }

  destroy() {
    this._destroyed = true;
    if (this._echo) {
      this._echo.disconnect();
      this._echo = null;
    }
    if (this._sseSource) {
      this._sseSource.close();
      this._sseSource = null;
    }
  }
}
