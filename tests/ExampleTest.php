<?php

use Eloquage\Lex\Lex;

it('bootstraps the package entrypoint', function () {
    $instance = new Lex;

    expect($instance->name())->toBe('lex');
});
