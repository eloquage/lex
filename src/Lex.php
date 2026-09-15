<?php

namespace Eloquage\Lex;

use InvalidArgumentException;

/**
 * Primary entrypoint for eloquage/lex.
 *
 * Pure-PHP implementation lives here. Optional TypePHP/native acceleration
 * can be added under native/ later without changing this public API.
 */
final class Lex
{
    /**
     * @var array<string, array{features: array<string, int>, length: int}>
     */
    private array $documents = [];

    /**
     * @var array<string, array<string, int>>
     */
    private array $postings = [];

    /**
     * @var array<string, int>
     */
    private array $documentFrequencies = [];

    private int $corpusSize = 0;

    private int $totalFeatureLength = 0;

    public function __construct(
        private readonly int $n = 1,
        private readonly float $k1 = 1.2,
        private readonly float $b = 0.75,
    ) {
        if ($this->n < 1) {
            throw new InvalidArgumentException('N-gram size must be positive.');
        }

        if (! is_finite($this->k1) || $this->k1 < 0) {
            throw new InvalidArgumentException('BM25 k1 must be finite and non-negative.');
        }

        if (! is_finite($this->b) || $this->b < 0 || $this->b > 1) {
            throw new InvalidArgumentException('BM25 b must be between 0 and 1.');
        }
    }

    public function name(): string
    {
        return 'lex';
    }

    public function add(string $id, string $text): void
    {
        self::validateId($id);
        $features = $this->features($text);

        if (array_key_exists($id, $this->documents)) {
            $this->remove($id);
        }

        $this->documents[$id] = [
            'features' => $features,
            'length' => array_sum($features),
        ];
        $this->corpusSize++;
        $this->totalFeatureLength += array_sum($features);

        foreach ($features as $feature => $frequency) {
            $this->postings[$feature][$id] = $frequency;
            $this->documentFrequencies[$feature] = ($this->documentFrequencies[$feature] ?? 0) + 1;
        }
    }

    /**
     * @return list<array{id: string, score: float}>
     */
    public function search(string $query, int $topK = 10): array
    {
        if ($topK <= 0) {
            throw new InvalidArgumentException('topK must be positive.');
        }

        $queryFeatures = array_keys($this->features($query));

        if ($queryFeatures === [] || $this->corpusSize === 0 || $this->totalFeatureLength === 0) {
            return [];
        }

        $averageLength = $this->totalFeatureLength / $this->corpusSize;
        $candidates = [];

        foreach ($queryFeatures as $feature) {
            foreach ($this->postings[$feature] ?? [] as $id => $frequency) {
                $candidates[$id] = true;
            }
        }

        $results = [];

        foreach (array_keys($candidates) as $id) {
            $document = $this->documents[$id];
            $score = 0.0;

            foreach ($queryFeatures as $feature) {
                $frequency = $document['features'][$feature] ?? 0;

                if ($frequency === 0) {
                    continue;
                }

                $documentFrequency = $this->documentFrequencies[$feature];
                $idf = log(1 + ($this->corpusSize - $documentFrequency + 0.5) / ($documentFrequency + 0.5));
                $normalization = 1 - $this->b + $this->b * $document['length'] / $averageLength;
                $termFrequency = ($frequency * ($this->k1 + 1)) / ($frequency + $this->k1 * $normalization);
                $score += $idf * $termFrequency;
            }

            $results[] = [
                'id' => $id,
                'score' => (float) $score,
            ];
        }

        usort($results, static function (array $left, array $right): int {
            if ($left['score'] !== $right['score']) {
                return $left['score'] > $right['score'] ? -1 : 1;
            }

            return strcmp($left['id'], $right['id']);
        });

        return array_slice($results, 0, $topK);
    }

    private static function validateId(string $id): void
    {
        if ($id === '') {
            throw new InvalidArgumentException('Document IDs must be non-empty strings.');
        }
    }

    /**
     * @return array<string, int>
     */
    private function features(string $text): array
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            throw new InvalidArgumentException('Text must contain valid UTF-8.');
        }

        $normalized = mb_strtolower($text, 'UTF-8');
        preg_match_all('/[\p{L}\p{M}\p{N}]+/u', $normalized, $matches);
        $tokens = $matches[0];

        if (count($tokens) < $this->n) {
            return [];
        }

        $features = [];

        for ($offset = 0, $last = count($tokens) - $this->n; $offset <= $last; $offset++) {
            $feature = implode("\x1F", array_slice($tokens, $offset, $this->n));
            $features[$feature] = ($features[$feature] ?? 0) + 1;
        }

        return $features;
    }

    private function remove(string $id): void
    {
        $document = $this->documents[$id];
        unset($this->documents[$id]);
        $this->corpusSize--;
        $this->totalFeatureLength -= $document['length'];

        foreach ($document['features'] as $feature => $frequency) {
            unset($this->postings[$feature][$id]);
            $this->documentFrequencies[$feature]--;

            if ($this->documentFrequencies[$feature] === 0) {
                unset($this->postings[$feature], $this->documentFrequencies[$feature]);
            }
        }
    }
}
