<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Formula;

use function hash;

/**
 * Creates stable content hashes for formula expressions.
 *
 * Hashing is intentionally layered on top of `FormulaCanonicalizer` so hash
 * equality reflects logical formula structure and values rather than the
 * incidental ordering of associative arrays in upstream input.
 *
 * The configured hashing algorithm is part of the caller-facing contract. If a
 * consumer persists these digests, changing the algorithm changes that digest
 * namespace.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class FormulaHasher
{
    /**
     * @param FormulaCanonicalizer $canonicalizer Canonicalization strategy applied before hashing
     * @param string               $algorithm     Hash algorithm understood by PHP's `hash` function
     */
    public function __construct(
        private FormulaCanonicalizer $canonicalizer = new FormulaCanonicalizer(),
        private string $algorithm = 'sha256',
    ) {}

    /**
     * Hash a formula after canonical normalization.
     *
     * Two formulas that differ only by associative key ordering produce the
     * same digest because canonicalization happens first.
     *
     * @param array<string, mixed> $expression
     */
    public function hash(array $expression): string
    {
        return hash($this->algorithm, $this->canonicalizer->canonicalize($expression));
    }
}
