# eloquage/lex

In-memory lexical retrieval for PHP with Unicode tokenization, contiguous word
n-grams, and deterministic Lucene+ BM25 ranking.

## Installation

```bash
composer require eloquage/lex
```

The package requires PHP 8.3 or newer and the `mbstring` extension. It has no
Laravel dependency and works through its pure-PHP implementation without a
TypePHP extension.

## Usage

Create one `Lex` instance, add string document IDs and text, then search them.
The default index uses individual tokens and returns the ten highest-ranked
matches.

```php
use Eloquage\Lex\Lex;

$lex = new Lex();
$lex->add('guide', 'PHP lexical search');
$lex->add('reference', 'BM25 ranks matching documents');

$results = $lex->search('LEXICAL');
// [['id' => 'guide', 'score' => 0.726...]]
```

Pass a positive n-gram size as the first constructor argument to index exact
contiguous word sequences. For example, `new Lex(2)` indexes bigrams and does
not add unigram features.

The optional BM25 parameters are `k1` and `b`. `k1` controls term-frequency
saturation. `b` controls document-length normalization. They default to `1.2`
and `0.75`, respectively. Results sort by descending score, then by ascending
bytewise document ID for stable ties. Adding an existing ID replaces its old
text.

## Testing

Run the package checks from this directory:

```bash
composer test
vendor/bin/pest --coverage --min=90
```

## Native scope

This change keeps `src/` as the complete implementation and explicitly skips
TypePHP native source. No native `.so` is required for the package behavior,
and no Composer TypePHP runtime dependency is added. See [TYPEPHP.md](TYPEPHP.md)
for the maintainer-only build contract and [AGENTS.md](AGENTS.md) for package
development guidance.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
