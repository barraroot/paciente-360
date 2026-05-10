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
    */
    'excluded_paths' => [
        '_ping',
        '_me',
    ],
];
