<?php

namespace App\Support;

/**
 * **T023** — Mapeia IANA timezone IDs para labels canônicas em pt-BR
 * (clarify nº 13 — qualificador no texto da mensagem ao paciente).
 *
 * Cobre os 4 fusos brasileiros vigentes + cidades mais comuns. Para fusos
 * não mapeados, faz fallback para o nome técnico do timezone (ex.:
 * `Europe/Lisbon` → "Europe/Lisbon").
 */
final class IanaTimezoneCity
{
    /**
     * @var array<string, string>
     */
    private const MAP = [
        // BR — UTC-03 (BRT)
        'America/Sao_Paulo' => 'horário de São Paulo',
        'America/Bahia' => 'horário de São Paulo',
        'America/Belem' => 'horário de São Paulo',
        'America/Fortaleza' => 'horário de São Paulo',
        'America/Maceio' => 'horário de São Paulo',
        'America/Recife' => 'horário de São Paulo',
        'America/Araguaina' => 'horário de São Paulo',
        'America/Santarem' => 'horário de São Paulo',

        // BR — UTC-04 (AMT)
        'America/Manaus' => 'horário de Manaus',
        'America/Boa_Vista' => 'horário de Manaus',
        'America/Porto_Velho' => 'horário de Manaus',
        'America/Cuiaba' => 'horário de Cuiabá',
        'America/Campo_Grande' => 'horário de Cuiabá',

        // BR — UTC-05 (ACT)
        'America/Rio_Branco' => 'horário do Acre',
        'America/Eirunepe' => 'horário do Acre',

        // BR — UTC-02 (FNT — Fernando de Noronha)
        'America/Noronha' => 'horário de Fernando de Noronha',
    ];

    public static function canonicalLabel(string $iana): string
    {
        return self::MAP[$iana] ?? $iana;
    }

    /**
     * Atalho usado no template de mensagem: "14:00 (horário de São Paulo)".
     */
    public static function format(string $hhmm, string $iana): string
    {
        return sprintf('%s (%s)', $hhmm, self::canonicalLabel($iana));
    }
}
