<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Formula\FormulaErrorCode;
use Cline\Formula\FormulaEvaluator;

it('rejects non iterable collection sources', function (): void {
    $evaluator = new FormulaEvaluator();

    $count = $evaluator->evaluate([
        'count' => ['var' => 'shipment.item'],
    ], [
        'shipment' => [
            'item' => 'not-an-array',
        ],
    ]);

    $sum = $evaluator->evaluate([
        'sum' => [
            'from' => ['var' => 'shipment.item'],
            'as' => 'item',
            'in' => ['var' => 'item.quantity'],
        ],
    ], [
        'shipment' => [
            'item' => 'not-an-array',
        ],
    ]);

    expect($count->succeeded)->toBeFalse()
        ->and($count->errorCode)->toBe(FormulaErrorCode::NonIterableInput)
        ->and($sum->succeeded)->toBeFalse()
        ->and($sum->errorCode)->toBe(FormulaErrorCode::NonIterableInput);
});

it('rejects invalid collection operator definitions', function (): void {
    $evaluator = new FormulaEvaluator();

    $map = $evaluator->evaluate([
        'map' => [
            'from' => ['var' => 'shipment.items'],
            'in' => ['var' => 'item.quantity'],
        ],
    ], [
        'shipment' => [
            'items' => [],
        ],
    ]);

    expect($map->succeeded)->toBeFalse()
        ->and($map->errorCode)->toBe(FormulaErrorCode::InvalidOperand);
});

it('rejects invalid collection projection value types', function (): void {
    $evaluator = new FormulaEvaluator();

    $sum = $evaluator->evaluate([
        'sum' => [
            'from' => ['var' => 'shipment.items'],
            'as' => 'item',
            'in' => ['var' => 'item.label'],
        ],
    ], [
        'shipment' => [
            'items' => [
                ['label' => 'heavy'],
            ],
        ],
    ]);

    $filter = $evaluator->evaluate([
        'filter' => [
            'from' => ['var' => 'shipment.items'],
            'as' => 'item',
            'where' => ['var' => 'item.label'],
        ],
    ], [
        'shipment' => [
            'items' => [
                ['label' => 'heavy'],
            ],
        ],
    ]);

    $max = $evaluator->evaluate([
        'maxOf' => [
            'from' => ['var' => 'shipment.items'],
            'as' => 'item',
            'in' => ['var' => 'item.label'],
        ],
    ], [
        'shipment' => [
            'items' => [
                ['label' => 'heavy'],
            ],
        ],
    ]);

    $min = $evaluator->evaluate([
        'minOf' => [
            'from' => ['var' => 'shipment.items'],
            'as' => 'item',
            'in' => ['var' => 'item.label'],
        ],
    ], [
        'shipment' => [
            'items' => [
                ['label' => 'heavy'],
            ],
        ],
    ]);

    expect($sum->succeeded)->toBeFalse()
        ->and($sum->errorCode)->toBe(FormulaErrorCode::InvalidOperand)
        ->and($filter->succeeded)->toBeFalse()
        ->and($filter->errorCode)->toBe(FormulaErrorCode::InvalidOperand)
        ->and($max->succeeded)->toBeFalse()
        ->and($max->errorCode)->toBe(FormulaErrorCode::InvalidOperand)
        ->and($min->succeeded)->toBeFalse()
        ->and($min->errorCode)->toBe(FormulaErrorCode::InvalidOperand);
});
