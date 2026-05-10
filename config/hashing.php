<?php

/*
|--------------------------------------------------------------------------
| Hashing — Paciente360
|--------------------------------------------------------------------------
|
| Driver default fixado em "argon2id" para atender o Princípio VII
| (Segurança Operacional) da constituição. Argon2id é resistente a
| ataques de tempo, GPU/ASIC e side-channel; é o algoritmo recomendado
| pela OWASP para senhas em 2025+.
|
| Bcrypt é mantido como fallback (12 rounds mínimo) caso a extensão
| Sodium/argon2 esteja indisponível em algum ambiente legado.
|
*/

return [

    'driver' => env('HASH_DRIVER', 'argon2id'),

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', true),
        'limit' => env('BCRYPT_LIMIT', null),
    ],

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', true),
    ],

    'rehash_on_login' => true,

];
