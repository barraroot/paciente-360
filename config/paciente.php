<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Timeline Tracked Fields
    |--------------------------------------------------------------------------
    |
    | Whitelist de campos e relações que geram entradas automáticas na timeline
    | do paciente quando alterados (R4 — Timeline Observer).
    |
    */

    'timeline' => [
        'tracked_fields' => [
            'status',
            'telefone_primario',
            'email',
            'profissional_responsavel_id',
            'convenio_principal_id',
        ],
        'tracked_relations' => [
            'tag',
            'convenio',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Import Limits
    |--------------------------------------------------------------------------
    |
    | Limites de tamanho e volume para importação de pacientes via CSV/XLSX.
    | Centraliza as restrições de R5 (Importação em lote).
    |
    */

    'import' => [
        'max_size_mb' => 5,
        'max_rows' => 10000,
        'batch_size' => 100,
    ],

];
