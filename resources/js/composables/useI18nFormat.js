/**
 * Composable com helpers de formatação localizada para pt-BR.
 *
 * Usa exclusivamente APIs nativas Intl.* — sem dependências adicionais.
 * O locale é fixo em 'pt-BR' na Fase 0 (MVP Brasil-only).
 */

const LOCALE = 'pt-BR';

/**
 * Formata uma data no padrão dd/MM/yyyy.
 *
 * @param {Date|string|number} date
 * @param {Intl.DateTimeFormatOptions} opts
 * @returns {string}
 */
export function formatDate(
    date,
    opts = { day: '2-digit', month: '2-digit', year: 'numeric' },
) {
    return new Intl.DateTimeFormat(LOCALE, opts).format(new Date(date));
}

/**
 * Formata uma data com hora no padrão dd/MM/yyyy HH:mm.
 *
 * @param {Date|string|number} date
 * @returns {string}
 */
export function formatDateTime(date) {
    return new Intl.DateTimeFormat(LOCALE, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(new Date(date));
}

/**
 * Formata um valor em centavos como moeda BRL.
 *
 * @param {number} valueInCents — valor em centavos (ex: 9900 → R$ 99,00)
 * @returns {string}
 */
export function formatCurrency(valueInCents) {
    return new Intl.NumberFormat(LOCALE, {
        style: 'currency',
        currency: 'BRL',
    }).format(valueInCents / 100);
}

/**
 * Formata um número de acordo com opções customizadas.
 *
 * @param {number} value
 * @param {Intl.NumberFormatOptions} opts
 * @returns {string}
 */
export function formatNumber(value, opts = {}) {
    return new Intl.NumberFormat(LOCALE, opts).format(value);
}

/**
 * Retorna uma string relativa ao momento atual, ex: "há 3 dias", "em 2 horas".
 *
 * @param {Date|string|number} date
 * @returns {string}
 */
export function formatRelative(date) {
    const rtf = new Intl.RelativeTimeFormat(LOCALE, { numeric: 'auto' });
    const diffMs = new Date(date).getTime() - Date.now();
    const diffSeconds = Math.round(diffMs / 1000);
    const diffMinutes = Math.round(diffSeconds / 60);
    const diffHours = Math.round(diffMinutes / 60);
    const diffDays = Math.round(diffHours / 24);
    const diffWeeks = Math.round(diffDays / 7);
    const diffMonths = Math.round(diffDays / 30);
    const diffYears = Math.round(diffDays / 365);

    if (Math.abs(diffYears) >= 1) {
        return rtf.format(diffYears, 'year');
    }
    if (Math.abs(diffMonths) >= 1) {
        return rtf.format(diffMonths, 'month');
    }
    if (Math.abs(diffWeeks) >= 1) {
        return rtf.format(diffWeeks, 'week');
    }
    if (Math.abs(diffDays) >= 1) {
        return rtf.format(diffDays, 'day');
    }
    if (Math.abs(diffHours) >= 1) {
        return rtf.format(diffHours, 'hour');
    }
    if (Math.abs(diffMinutes) >= 1) {
        return rtf.format(diffMinutes, 'minute');
    }

    return rtf.format(diffSeconds, 'second');
}

/**
 * Hook composable para uso em componentes Vue.
 * Retorna todos os helpers de formatação agrupados.
 */
export function useI18nFormat() {
    return {
        formatDate,
        formatDateTime,
        formatCurrency,
        formatNumber,
        formatRelative,
    };
}
