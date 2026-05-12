<?php

namespace App\Domain\Messaging\Widget\Services;

use Carbon\Carbon;

/**
 * T218 — Avalia se o momento atual está dentro do horário comercial configurado.
 *
 * Shape de `business_hours`:
 * ```json
 * {
 *   "monday": "08:00-18:00",
 *   "tuesday": "08:00-18:00",
 *   "wednesday": null,
 *   "thursday": "08:00-18:00",
 *   "friday": "08:00-17:00",
 *   "saturday": null,
 *   "sunday": null,
 *   "timezone": "America/Sao_Paulo"
 * }
 * ```
 *
 * Null ou string vazia para um dia = fechado naquele dia.
 * Array vazio = sempre aberto.
 */
final class BusinessHoursEvaluator
{
    /** @var array<int, string> Mapeamento índice Carbon (0=Sunday) para chave do array */
    private const DAY_MAP = [
        0 => 'sunday',
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
    ];

    /**
     * Verifica se o horário atual está dentro do horário comercial.
     *
     * @param array<string, mixed> $businessHours
     */
    public function isWithinBusinessHours(array $businessHours, ?Carbon $now = null): bool
    {
        // Empty config = always open
        if (empty($businessHours)) {
            return true;
        }

        $timezone = $businessHours['timezone'] ?? 'America/Sao_Paulo';
        $now = ($now ?? Carbon::now())->setTimezone($timezone);

        $dayKey = self::DAY_MAP[$now->dayOfWeek] ?? null;
        if ($dayKey === null) {
            return false;
        }

        $range = $businessHours[$dayKey] ?? null;

        // Null = closed today
        if ($range === null || $range === '') {
            return false;
        }

        return $this->isTimeWithinRange((string) $range, $now);
    }

    /**
     * Verifica se o momento atual está dentro do range de horário.
     *
     * @param string $range Formato "HH:MM-HH:MM"
     */
    private function isTimeWithinRange(string $range, Carbon $now): bool
    {
        $parts = explode('-', $range, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$startStr, $endStr] = $parts;

        $start = $this->parseTime($startStr, $now);
        $end = $this->parseTime($endStr, $now);

        if ($start === null || $end === null) {
            return false;
        }

        $currentMinutes = ($now->hour * 60) + $now->minute;

        return $currentMinutes >= $start && $currentMinutes < $end;
    }

    /**
     * Parseia "HH:MM" em minutos totais desde meia-noite.
     */
    private function parseTime(string $time, Carbon $reference): ?int
    {
        $time = trim($time);
        $parts = explode(':', $time, 2);

        if (count($parts) !== 2) {
            return null;
        }

        $hours = (int) $parts[0];
        $minutes = (int) $parts[1];

        return ($hours * 60) + $minutes;
    }
}
