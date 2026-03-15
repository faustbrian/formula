<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Formula\FormulaErrorCode;
use Cline\Formula\FormulaEvaluator;

it('propagates collection source resolution failures', function (): void {
    $evaluator = new FormulaEvaluator();

    $filter = $evaluator->evaluate([
        'filter' => [
            'from' => ['var' => 'shipment.items'],
            'as' => 'item',
            'where' => ['const' => true],
        ],
    ], []);

    $maxOf = $evaluator->evaluate([
        'maxOf' => [
            'from' => ['var' => 'shipment.items'],
            'as' => 'item',
            'in' => ['var' => 'item.weight'],
        ],
    ], []);

    $minOf = $evaluator->evaluate([
        'minOf' => [
            'from' => ['var' => 'shipment.items'],
            'as' => 'item',
            'in' => ['var' => 'item.weight'],
        ],
    ], []);

    expect($filter->succeeded)->toBeFalse()
        ->and($filter->errorCode)->toBe(FormulaErrorCode::MissingVariable)
        ->and($maxOf->succeeded)->toBeFalse()
        ->and($maxOf->errorCode)->toBe(FormulaErrorCode::MissingVariable)
        ->and($minOf->succeeded)->toBeFalse()
        ->and($minOf->errorCode)->toBe(FormulaErrorCode::MissingVariable);
});

it('propagates collection projection failures', function (): void {
    $evaluator = new FormulaEvaluator();

    $sum = $evaluator->evaluate([
        'sum' => [
            'from' => ['const' => [['label' => 'heavy']]],
            'as' => 'item',
            'in' => ['var' => 'item.weight'],
        ],
    ], []);

    $filter = $evaluator->evaluate([
        'filter' => [
            'from' => ['const' => [['label' => 'heavy']]],
            'as' => 'item',
            'where' => ['var' => 'item.enabled'],
        ],
    ], []);

    $map = $evaluator->evaluate([
        'map' => [
            'from' => ['const' => [['label' => 'heavy']]],
            'as' => 'item',
            'in' => ['var' => 'item.weight'],
        ],
    ], []);

    expect($sum->succeeded)->toBeFalse()
        ->and($sum->errorCode)->toBe(FormulaErrorCode::MissingVariable)
        ->and($filter->succeeded)->toBeFalse()
        ->and($filter->errorCode)->toBe(FormulaErrorCode::MissingVariable)
        ->and($map->succeeded)->toBeFalse()
        ->and($map->errorCode)->toBe(FormulaErrorCode::MissingVariable);
});

it('propagates collection definition failures from source evaluation', function (): void {
    $evaluator = new FormulaEvaluator();

    $sum = $evaluator->evaluate([
        'sum' => [
            'from' => ['var' => 'shipment.items'],
            'as' => 'item',
            'in' => 1,
        ],
    ], []);

    expect($sum->succeeded)->toBeFalse()
        ->and($sum->errorCode)->toBe(FormulaErrorCode::InvalidOperand);
});
