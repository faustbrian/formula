<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Formula\FormulaEvaluator;

it('sums values from collection items through scoped variables', function (): void {
    $evaluator = new FormulaEvaluator();

    $result = $evaluator->evaluate([
        'sum' => [
            'from' => ['var' => 'shipment.items'],
            'as' => 'item',
            'in' => [
                'multiply' => [
                    ['var' => 'item.quantity'],
                    ['var' => 'item.unit_value.amount'],
                ],
            ],
        ],
    ], [
        'shipment' => [
            'items' => [
                [
                    'quantity' => 2,
                    'unit_value' => ['amount' => 10.5],
                ],
                [
                    'quantity' => 1,
                    'unit_value' => ['amount' => 4.0],
                ],
            ],
        ],
    ]);

    expect($result->succeeded)->toBeTrue()
        ->and($result->value)->toBe(25.0);
});

it('filters, maps, and counts collection items through local scope aliases', function (): void {
    $evaluator = new FormulaEvaluator();

    $filtered = $evaluator->evaluate([
        'filter' => [
            'from' => ['var' => 'shipment.items'],
            'as' => 'item',
            'where' => ['greaterThan' => [['var' => 'item.quantity'], 1]],
        ],
    ], [
        'shipment' => [
            'items' => [
                ['sku' => 'A', 'quantity' => 2],
                ['sku' => 'B', 'quantity' => 1],
                ['sku' => 'C', 'quantity' => 3],
            ],
        ],
    ]);

    expect($filtered->succeeded)->toBeTrue()
        ->and($filtered->value)->toBe([
            ['sku' => 'A', 'quantity' => 2],
            ['sku' => 'C', 'quantity' => 3],
        ]);

    $mapped = $evaluator->evaluate([
        'map' => [
            'from' => ['var' => 'shipment.items'],
            'as' => 'item',
            'in' => ['var' => 'item.sku'],
        ],
    ], [
        'shipment' => [
            'items' => [
                ['sku' => 'A', 'quantity' => 2],
                ['sku' => 'B', 'quantity' => 1],
                ['sku' => 'C', 'quantity' => 3],
            ],
        ],
    ]);

    expect($mapped->succeeded)->toBeTrue()
        ->and($mapped->value)->toBe(['A', 'B', 'C']);

    $count = $evaluator->evaluate([
        'count' => ['var' => 'shipment.items'],
    ], [
        'shipment' => [
            'items' => [
                ['sku' => 'A'],
                ['sku' => 'B'],
                ['sku' => 'C'],
            ],
        ],
    ]);

    expect($count->succeeded)->toBeTrue()
        ->and($count->value)->toBe(3);
});

it('derives min and max values from projected collections', function (): void {
    $evaluator = new FormulaEvaluator();

    $max = $evaluator->evaluate([
        'maxOf' => [
            'from' => ['var' => 'shipment.parcels'],
            'as' => 'parcel',
            'in' => ['var' => 'parcel.weight'],
        ],
    ], [
        'shipment' => [
            'parcels' => [
                ['weight' => 1.2],
                ['weight' => 3.4],
                ['weight' => 2.6],
            ],
        ],
    ]);

    $min = $evaluator->evaluate([
        'minOf' => [
            'from' => ['var' => 'shipment.parcels'],
            'as' => 'parcel',
            'in' => ['var' => 'parcel.weight'],
        ],
    ], [
        'shipment' => [
            'parcels' => [
                ['weight' => 1.2],
                ['weight' => 3.4],
                ['weight' => 2.6],
            ],
        ],
    ]);

    expect($max->succeeded)->toBeTrue()
        ->and($max->value)->toBe(3.4)
        ->and($min->succeeded)->toBeTrue()
        ->and($min->value)->toBe(1.2);
});
