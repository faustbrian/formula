<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Formula\FormulaCanonicalizer;
use Cline\Formula\FormulaHasher;

it('canonicalizes and hashes formulas deterministically', function (): void {
    $canonicalizer = new FormulaCanonicalizer();
    $hasher = new FormulaHasher($canonicalizer);

    $left = [
        'add' => [
            ['var' => 'totals.gross_weight'],
            ['const' => 1],
        ],
        'metadata' => [
            'b' => 2,
            'a' => 1,
        ],
    ];

    $right = [
        'metadata' => [
            'a' => 1,
            'b' => 2,
        ],
        'add' => [
            ['var' => 'totals.gross_weight'],
            ['const' => 1],
        ],
    ];

    expect($canonicalizer->canonicalize($left))
        ->toBe($canonicalizer->canonicalize($right))
        ->and($hasher->hash($left))
        ->toBe($hasher->hash($right));
});

it('supports configurable hashing algorithms', function (): void {
    $hasher = new FormulaHasher(
        new FormulaCanonicalizer(),
        'md5',
    );

    expect($hasher->hash(['const' => 10]))
        ->toHaveLength(32);
});

it('throws a runtime exception when canonicalization cannot encode the expression', function (): void {
    $canonicalizer = new FormulaCanonicalizer();

    expect(fn (): string => $canonicalizer->canonicalize([
        'const' => \INF,
    ]))->toThrow(RuntimeException::class);
});
