<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Formula\FormulaErrorCode;
use Cline\Formula\FormulaEvaluator;

it('evaluates remaining scalar comparison and logical operators', function (): void {
    $evaluator = new FormulaEvaluator();

    $notEquals = $evaluator->evaluate([
        'notEquals' => [['var' => 'left'], ['var' => 'right']],
    ], [
        'left' => 'FI',
        'right' => 'SE',
    ]);

    $greaterThanOrEqual = $evaluator->evaluate([
        'greaterThanOrEqual' => [['var' => 'weight'], 10],
    ], [
        'weight' => 10,
    ]);

    $lessThan = $evaluator->evaluate([
        'lessThan' => [['var' => 'weight'], 11],
    ], [
        'weight' => 10,
    ]);

    $or = $evaluator->evaluate([
        'or' => [false, true],
    ], []);

    $divide = $evaluator->evaluate([
        'divide' => [20, 4],
    ], []);

    $min = $evaluator->evaluate([
        'min' => [8, 3, 5],
    ], []);

    $pathWithEmptySegment = $evaluator->evaluate([
        'var' => 'shipment..weight',
    ], [
        'shipment' => [
            'weight' => 7.5,
        ],
    ]);

    expect($notEquals->succeeded)->toBeTrue()
        ->and($notEquals->value)->toBeTrue()
        ->and($greaterThanOrEqual->succeeded)->toBeTrue()
        ->and($greaterThanOrEqual->value)->toBeTrue()
        ->and($lessThan->succeeded)->toBeTrue()
        ->and($lessThan->value)->toBeTrue()
        ->and($or->succeeded)->toBeTrue()
        ->and($or->value)->toBeTrue()
        ->and($divide->succeeded)->toBeTrue()
        ->and($divide->value)->toBe(5.0)
        ->and($min->succeeded)->toBeTrue()
        ->and($min->value)->toBe(3.0)
        ->and($pathWithEmptySegment->succeeded)->toBeTrue()
        ->and($pathWithEmptySegment->value)->toBe(7.5);
});

it('propagates scalar operator failures from nested operands', function (): void {
    $evaluator = new FormulaEvaluator();

    $predicate = $evaluator->evaluate([
        'predicate' => ['field' => 'status'],
    ], []);

    $count = $evaluator->evaluate([
        'count' => ['var' => 'shipment.items'],
    ], []);

    $divide = $evaluator->evaluate([
        'divide' => [['var' => 'left'], ['var' => 'right']],
    ], [
        'left' => 10,
    ]);

    $max = $evaluator->evaluate([
        'max' => [['var' => 'left'], ['var' => 'right']],
    ], [
        'left' => 10,
    ]);

    $min = $evaluator->evaluate([
        'min' => [['var' => 'left'], ['var' => 'right']],
    ], [
        'left' => 10,
    ]);

    $not = $evaluator->evaluate([
        'not' => ['var' => 'enabled'],
    ], []);

    $if = $evaluator->evaluate([
        'if' => [
            ['var' => 'enabled'],
            1,
            0,
        ],
    ], []);

    $or = $evaluator->evaluate([
        'or' => [true, ['var' => 'enabled']],
    ], []);

    expect($predicate->succeeded)->toBeFalse()
        ->and($predicate->errorCode)->toBe(FormulaErrorCode::InvalidOperand)
        ->and($count->succeeded)->toBeFalse()
        ->and($count->errorCode)->toBe(FormulaErrorCode::MissingVariable)
        ->and($divide->succeeded)->toBeFalse()
        ->and($divide->errorCode)->toBe(FormulaErrorCode::MissingVariable)
        ->and($max->succeeded)->toBeFalse()
        ->and($max->errorCode)->toBe(FormulaErrorCode::MissingVariable)
        ->and($min->succeeded)->toBeFalse()
        ->and($min->errorCode)->toBe(FormulaErrorCode::MissingVariable)
        ->and($not->succeeded)->toBeFalse()
        ->and($not->errorCode)->toBe(FormulaErrorCode::MissingVariable)
        ->and($if->succeeded)->toBeFalse()
        ->and($if->errorCode)->toBe(FormulaErrorCode::MissingVariable)
        ->and($or->succeeded)->toBeFalse()
        ->and($or->errorCode)->toBe(FormulaErrorCode::MissingVariable);
});

it('covers coalesce and comparison failure propagation branches', function (): void {
    $evaluator = new FormulaEvaluator();

    $invalidCoalesce = $evaluator->evaluate([
        'coalesce' => ['var' => 'shipment.weight'],
    ], []);

    $nullCoalesce = $evaluator->evaluate([
        'coalesce' => [null, ['const' => null]],
    ], []);

    $equalsRightFailure = $evaluator->evaluate([
        'equals' => [1, ['var' => 'missing']],
    ], []);

    $notEqualsLeftFailure = $evaluator->evaluate([
        'notEquals' => [['var' => 'missing'], 1],
    ], []);

    $notEqualsRightFailure = $evaluator->evaluate([
        'notEquals' => [1, ['var' => 'missing']],
    ], []);

    $numericRightFailure = $evaluator->evaluate([
        'greaterThan' => [1, ['var' => 'missing']],
    ], []);

    $numericLeftFailure = $evaluator->evaluate([
        'lessThan' => [['var' => 'missing'], 2],
    ], []);

    $booleanNonList = $evaluator->evaluate([
        'or' => true,
    ], []);

    expect($invalidCoalesce->succeeded)->toBeFalse()
        ->and($invalidCoalesce->errorCode)->toBe(FormulaErrorCode::InvalidOperand)
        ->and($nullCoalesce->succeeded)->toBeTrue()
        ->and($nullCoalesce->value)->toBeNull()
        ->and($equalsRightFailure->succeeded)->toBeFalse()
        ->and($equalsRightFailure->errorCode)->toBe(FormulaErrorCode::MissingVariable)
        ->and($notEqualsLeftFailure->succeeded)->toBeFalse()
        ->and($notEqualsLeftFailure->errorCode)->toBe(FormulaErrorCode::MissingVariable)
        ->and($notEqualsRightFailure->succeeded)->toBeFalse()
        ->and($notEqualsRightFailure->errorCode)->toBe(FormulaErrorCode::MissingVariable)
        ->and($numericRightFailure->succeeded)->toBeFalse()
        ->and($numericRightFailure->errorCode)->toBe(FormulaErrorCode::MissingVariable)
        ->and($numericLeftFailure->succeeded)->toBeFalse()
        ->and($numericLeftFailure->errorCode)->toBe(FormulaErrorCode::MissingVariable)
        ->and($booleanNonList->succeeded)->toBeFalse()
        ->and($booleanNonList->errorCode)->toBe(FormulaErrorCode::InvalidOperand);
});
