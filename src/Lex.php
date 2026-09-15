<?php

namespace Eloquage\Lex;

/**
 * Primary entrypoint for eloquage/lex.
 *
 * Pure-PHP implementation lives here. Optional TypePHP/native acceleration
 * can be added under native/ later without changing this public API.
 */
final class Lex
{
    public function name(): string
    {
        return 'lex';
    }
}
