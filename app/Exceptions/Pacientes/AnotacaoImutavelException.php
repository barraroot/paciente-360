<?php

namespace App\Exceptions\Pacientes;

use RuntimeException;

/**
 * T029 — Lançada quando há tentativa de editar ou excluir uma anotação.
 *
 * Anotações são imutáveis por design clínico/jurídico: uma anotação
 * registrada nunca pode ser alterada — apenas retratada (nova anotação
 * com `retratacao_de_anotacao_id` preenchido).
 *
 * O handler em `bootstrap/app.php` converte para HTTP 409 com body JSON.
 */
final class AnotacaoImutavelException extends RuntimeException {}
