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
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class FormulaHasher
{
    public function __construct(
        private FormulaCanonicalizer $canonicalizer = new FormulaCanonicalizer(),
        private string $algorithm = 'sha256',
    ) {}

    /**
     * @param array<string, mixed> $expression
     */
    public function hash(array $expression): string
    {
        return hash($this->algorithm, $this->canonicalizer->canonicalize($expression));
    }
}
