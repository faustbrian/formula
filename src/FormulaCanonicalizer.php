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
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class FormulaCanonicalizer
{
    /**
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
