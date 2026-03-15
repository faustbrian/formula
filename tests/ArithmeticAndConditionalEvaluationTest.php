<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Formula\FormulaEvaluator;

it('evaluates nested scalar arithmetic and conditional branches', function (): void {
    $evaluator = new FormulaEvaluator();

    $result = $evaluator->evaluate([
        'if' => [
            ['equals' => [['var' => 'shipment.route.origin'], ['var' => 'shipment.route.destination']]],
            ['max' => [
                ['subtract' => [10, 4]],
                ['add' => [1, 2, 3]],
            ]],
            0,
        ],
    ], [
        'shipment' => [
            'route' => [
                'origin' => 'FI',
                'destination' => 'FI',
            ],
        ],
    ]);

    expect($result->succeeded)->toBeTrue()
        ->and($result->value)->toBe(6.0);
});

it('resolves variables with defaults and coalesce semantics', function (): void {
    $evaluator = new FormulaEvaluator();

    $result = $evaluator->evaluate([
        'coalesce' => [
            ['var' => 'shipment.optional.total'],
            ['var' => 'shipment.fallback.total', 'default' => 25],
            99,
        ],
    ], [
        'shipment' => [],
    ]);

    expect($result->succeeded)->toBeTrue()
        ->and($result->value)->toBe(25);
});

it('evaluates boolean combinators and numeric comparisons', function (): void {
    $evaluator = new FormulaEvaluator();

    $result = $evaluator->evaluate([
        'and' => [
            ['greaterThan' => [['var' => 'metrics.gross_weight'], 10]],
            ['lessThanOrEqual' => [['var' => 'metrics.package_count'], 5]],
            ['not' => ['equals' => [['var' => 'route.country'], 'SE']]],
        ],
    ], [
        'metrics' => [
            'gross_weight' => 12.5,
            'package_count' => 4,
        ],
        'route' => [
            'country' => 'FI',
        ],
    ]);

    expect($result->succeeded)->toBeTrue()
        ->and($result->value)->toBeTrue();
});
