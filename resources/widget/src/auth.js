/**
 * T222 — Gerenciamento de sessão do visitante via localStorage.
 *
 * visitor_token: token opaco de 64 chars hex, gerado pelo backend na primeira visita.
 * Persistido em localStorage para continuidade entre recarregamentos.
 *
 * Idempotency-Key: UUID v4 gerado por mensagem para deduplicação server-side.
 */

const STORAGE_KEY = 'p360_widget_visitor_token';

/**
 * Retorna o visitor_token salvo ou null.
 *
 * @returns {string|null}
 */
export function getVisitorToken() {
  try {
    return localStorage.getItem(STORAGE_KEY);
  } catch {
    return null;
  }
}

/**
 * Persiste o visitor_token retornado pelo backend.
 *
 * @param {string} token
 */
export function saveVisitorToken(token) {
  try {
    localStorage.setItem(STORAGE_KEY, token);
  } catch {
    // localStorage indisponível (modo incógnito restrito, etc.) — ignora silenciosamente
  }
}

/**
 * Remove o visitor_token (logout ou sessão expirada).
 */
export function clearVisitorToken() {
  try {
    localStorage.removeItem(STORAGE_KEY);
  } catch {
    // ignora
  }
}

/**
 * Gera um UUID v4 para usar como Idempotency-Key por mensagem enviada.
 *
 * Usa crypto.randomUUID() quando disponível (navegadores modernos).
 * Fallback: Math.random() para ambientes legados (IE11 não suportado).
 *
 * @returns {string}
 */
export function generateIdempotencyKey() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  // Fallback UUID v4 manual
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}
