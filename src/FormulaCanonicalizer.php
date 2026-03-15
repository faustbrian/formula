<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Formula;

use JsonException;
use RuntimeException;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

use function array_is_list;
use function array_map;
use function is_array;
use function json_encode;
use function ksort;

/**
 * Produces a deterministic serialized representation of a formula tree.
 *
 * Canonicalization exists so semantically identical associative structures
 * produce the same byte sequence regardless of input key ordering. This is the
 * normalization step consumed by `FormulaHasher` before hashing.
 *
 * Positional list ordering is preserved because list order is meaningful for
 * operators such as `if`, arithmetic pairs, and comparisons. Only associative
 * arrays are key-sorted recursively.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class FormulaCanonicalizer
{
    /**
     * Serialize a formula after recursively normalizing associative key order.
     *
     * Unescaped slashes are used to avoid incidental digest differences when
     * canonicalized output is later hashed. Low-level JSON failures are wrapped
     * in `RuntimeException` because canonicalization is treated as an
     * infrastructure concern, not a normal evaluation error.
     *
     * @param array<string, mixed> $expression
     */
    public function canonicalize(array $expression): string
    {
        try {
            return json_encode(
                $this->sortRecursively($expression),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $jsonException) {
            throw new RuntimeException('Unable to canonicalize formula expression.', $jsonException->getCode(), previous: $jsonException);
        }
    }

    /**
     * Recursively normalize associative ordering while preserving list order.
     *
     * Scalars are returned unchanged. Lists are traversed in order, while
     * associative arrays are copied, normalized entry-by-entry, and key-sorted
     * to make the final JSON representation deterministic.
     */
    private function sortRecursively(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->sortRecursively(...), $value);
        }

        $sorted = [];

        foreach ($value as $key => $entry) {
            $sorted[$key] = $this->sortRecursively($entry);
        }

        ksort($sorted);

        return $sorted;
    }
}
