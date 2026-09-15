<?php

use Eloquage\Lex\Lex;

it('keeps the public API narrow and works without a native extension', function () {
    $lex = new Lex;

    expect($lex->name())->toBe('lex')
        ->and($lex->search('anything'))->toBe([])
        ->and(array_values(array_filter(
            get_class_methods(Lex::class),
            static fn (string $method): bool => $method !== '__construct',
        )))->toBe(['name', 'add', 'search']);
});

it('indexes case-insensitive lexical tokens with Unicode letters, marks, and numbers', function () {
    $lex = new Lex;
    $lex->add('unicode', 'CAFÉ 東京 ÉLAN 2026!');

    expect($lex->search('café 東京 éLAN 2026'))->toHaveCount(1)
        ->and($lex->search('café 東京 éLAN 2026')[0]['id'])->toBe('unicode')
        ->and($lex->search('café 東京 éLAN 2026')[0]['score'])->toBeGreaterThan(0.0);
});

it('treats punctuation and symbols as token boundaries', function () {
    $lex = new Lex;
    $lex->add('quick', 'Quick, BROWN fox!');

    expect($lex->search('brown'))->toHaveCount(1)
        ->and($lex->search('brown, fox'))->toHaveCount(1)
        ->and($lex->search('brown-fox'))->toHaveCount(1)
        ->and($lex->search('brownfox'))->toBe([]);
});

it('rejects invalid IDs, UTF-8, n-gram sizes, and scoring parameters', function () {
    expect(fn () => new Lex(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Lex(1, -0.1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Lex(1, 1.2, -0.1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Lex(1, 1.2, 1.1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Lex(1, NAN))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new Lex)->add('', 'text'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new Lex)->add('bad', "\xFF"))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new Lex)->search("\xFF"))->toThrow(InvalidArgumentException::class);
});

it('matches only contiguous exact n-grams', function () {
    $lex = new Lex(2);
    $lex->add('d1', 'red fox jumps');

    expect($lex->search('fox jumps'))->toHaveCount(1)
        ->and($lex->search('red jumps'))->toBe([])
        ->and($lex->search('fox'))->toBe([])
        ->and($lex->search('red fox jumps'))->toHaveCount(1);
});

it('replaces a document and preserves the old index on invalid replacement input', function () {
    $lex = new Lex;
    $lex->add('doc-1', 'blue ocean');
    $lex->add('doc-1', 'green forest');

    expect($lex->search('ocean'))->toBe([])
        ->and($lex->search('forest')[0]['id'])->toBe('doc-1');

    expect(fn () => $lex->add('doc-1', "green \xFF"))->toThrow(InvalidArgumentException::class)
        ->and($lex->search('forest')[0]['id'])->toBe('doc-1');
});

it('uses the Lucene-plus BM25 formula and counts each query feature once', function () {
    $lex = new Lex;
    $lex->add('guide', 'PHP lexical search');

    $expected = log(1 + (1 - 1 + 0.5) / (1 + 0.5));
    $score = $lex->search('lexical lexical')[0]['score'];

    expect($score)->toBe($expected)
        ->and($score)->toBeGreaterThan(0.0)
        ->and($lex->search('lexical')[0]['score'])->toBe($score);
});

it('normalizes document length and saturates repeated term frequency', function () {
    $lex = new Lex;
    $lex->add('short', 'alpha');
    $lex->add('long', 'alpha alpha alpha');

    $results = $lex->search('alpha');
    $short = $results[array_search('short', array_column($results, 'id'), true)]['score'];
    $long = $results[array_search('long', array_column($results, 'id'), true)]['score'];
    $idf = log(1 + (2 - 2 + 0.5) / (2 + 0.5));
    $shortExpected = $idf * (2.2 / (1 + 1.2 * (0.25 + 0.75 * (1 / 2))));
    $longExpected = $idf * (6.6 / (3 + 1.2 * (0.25 + 0.75 * (3 / 2))));

    expect($short)->toBe($shortExpected)
        ->and($long)->toBe($longExpected)
        ->and($long)->toBeGreaterThan($short)
        ->and(($long / $short))->toBeLessThan(3.0);
});

it('returns deterministic top-k results and orders ties by bytewise ID', function () {
    $lex = new Lex;
    $lex->add('b', 'same');
    $lex->add('a', 'same');
    $lex->add('c', 'other');

    expect(array_column($lex->search('same'), 'id'))->toBe(['a', 'b'])
        ->and($lex->search('same', 1))->toHaveCount(1)
        ->and(fn () => $lex->search('same', 0))->toThrow(InvalidArgumentException::class);
});

it('returns empty results for empty, unknown, and non-feature queries', function () {
    $lex = new Lex;
    $lex->add('empty', '');
    $lex->add('green', 'green');

    expect($lex->search(''))->toBe([])
        ->and($lex->search('   ,.!'))->toBe([])
        ->and($lex->search('purple'))->toBe([])
        ->and($lex->search('green')[0]['id'])->toBe('green');
});

it('does not add semantic, stemmed, or fuzzy matches', function () {
    $lex = new Lex;
    $lex->add('car', 'car running');

    expect($lex->search('automobile run'))->toBe([])
        ->and($lex->search('cars'))->toBe([])
        ->and($lex->search('runn'))->toBe([]);
});

it('scores more equal-length query overlap higher', function () {
    $lex = new Lex;
    $lex->add('d1', 'alpha gamma');
    $lex->add('d2', 'alpha beta');

    $results = $lex->search('alpha beta');

    expect($results[0]['id'])->toBe('d2')
        ->and($results[0]['score'])->toBeGreaterThan($results[1]['score']);
});
