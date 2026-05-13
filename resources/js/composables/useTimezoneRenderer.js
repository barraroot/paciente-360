/**
 * T086 — Conversão TZ no cliente via Luxon (clarify nº 13 / R6).
 *
 * Recebe ISO 8601 com offset (`2026-06-15T14:00:00-03:00`) e renderiza no TZ
 * do contexto (tenant ou profissional via TimezoneResolverService).
 */
import { DateTime } from 'luxon'

export function useTimezoneRenderer(contextTimezone) {
  const tz = contextTimezone || 'America/Sao_Paulo'

  const parse = (iso) => DateTime.fromISO(iso, { setZone: true }).setZone(tz)

  const formatDateTime = (iso, format = 'dd/MM/yyyy HH:mm') => parse(iso).toFormat(format)

  const formatTime = (iso) => parse(iso).toFormat('HH:mm')

  const formatDate = (iso) => parse(iso).toFormat('dd/MM/yyyy')

  return { parse, formatDateTime, formatTime, formatDate, tz }
}
