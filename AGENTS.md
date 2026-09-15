# eloquage/lex

Lexical retrieval for PHP: Unicode tokenization, exact word n-grams, and
Lucene+ BM25 ranking.

- Composer: `eloquage/lex`
- Entrypoint: `Eloquage\Lex\Lex`
- Platform requirement: PHP 8.3+ and `ext-mbstring`
- The package is framework-agnostic. The Laravel app at the monorepo root is a
  local test bench only.

## Public API

`Lex` is the only public search type. Construct it with an optional positive
n-gram size and BM25 parameters, add documents with `add(string $id, string
$text)`, and retrieve `list<array{id: string, score: float}>` results with
`search(string $query, int $topK = 10)`. Tokenization, postings, corpus
statistics, and scoring remain private.

## Layout

- `src/` — public PHP API and pure-PHP source of truth
- `native/` — empty; no native implementation is in scope for this change
- `tests/` — Pest 5 interface tests
- `TYPEPHP.md` — optional maintainer build contract

## Commands

```bash
composer test
vendor/bin/pest --coverage --min=90
composer format
```

## Conventions

- No Illuminate or Laravel service providers.
- Keep the pure-PHP implementation complete and independently usable.
- Do not add a Composer TypePHP runtime dependency.
- Native TypePHP compilation is explicitly skipped for `lex-bm25-inverted-index`.
  Do not add native source, `main()`, `libphp.so`, or a required `.so` path as
  part of this change.
- Consumers use PHP 8.3+. Package CI uses PHP 8.4. The optional TypePHP builder
  targets PHP 8.5 syntax.

## Harness demo

The root welcome page (`/`) exercises the public API with a fixed Lex sample.
Keep that adapter in the Laravel app, not in the package.
