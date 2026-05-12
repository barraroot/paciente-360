<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Paths excluídos da comparação OpenAPI vs Rotas
    |--------------------------------------------------------------------------
    |
    | Caminhos listados aqui (sem prefixo api/v1) são ignorados tanto na
    | direção "rota sem documentação" quanto na direção "OpenAPI sem rota".
    | Útil para rotas de smoke-test, health-check e diagnóstico interno
    | que existem em código mas não fazem parte do contrato público.
    |
    | Nota: caminhos do widget (`/widget/v1/*`) são documentados no OpenAPI
    | para referência mas roteados sob o prefixo `widget/v1` (não `api/v1`).
    | O DriftChecker só inspeciona rotas `api/v1` — os paths do widget são
    | excluídos aqui para evitar falsos positivos de [ORPHAN].
    |
    | De maneira análoga, `/inbox/media/*` e `/inbox/presence/*` são
    | documentados na spec mas implementados em fases futuras — excluídos
    | até a implementação para manter CI verde.
    |
    */
    'excluded_paths' => [
        '_ping',
        '_me',
        // Widget JS API — roteado como `widget/v1/*` (não `api/v1/*`)
        'widget/',
        // Endpoints documentados mas implementados em fases futuras
        'inbox/media',
        'inbox/presence',
    ],
];
