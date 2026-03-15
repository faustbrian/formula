<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Formula\FormulaErrorCode;
use Cline\Formula\FormulaEvaluator;

it('rejects empty and malformed expressions', function (): void {
    $evaluator = new FormulaEvaluator();

    $empty = $evaluator->evaluate([], []);
    $multiOperator = $evaluator->evaluate([
        'add' => [1, 2],
        'multiply' => [3, 4],
    ], []);

    expect($empty->succeeded)->toBeFalse()
        ->and($empty->errorCode)->toBe(FormulaErrorCode::InvalidExpression)
        ->and($multiOperator->succeeded)->toBeFalse()
        ->and($multiOperator->errorCode)->toBe(FormulaErrorCode::InvalidExpression);
});

it('rejects invalid variable paths and unsupported operators', function (): void {
    $evaluator = new FormulaEvaluator();

    $invalidPath = $evaluator->evaluate([
        'var' => '',
    ], []);

    $unsupported = $evaluator->evaluate([
        'modulo' => [7, 2],
    ], []);

    expect($invalidPath->succeeded)->toBeFalse()
        ->and($invalidPath->errorCode)->toBe(FormulaErrorCode::InvalidPath)
        ->and($unsupported->succeeded)->toBeFalse()
        ->and($unsupported->errorCode)->toBe(FormulaErrorCode::UnsupportedOperator);
});

it('rejects invalid numeric operator inputs and arity mismatches', function (): void {
    $evaluator = new FormulaEvaluator();

    $notAList = $evaluator->evaluate([
        'add' => ['var' => 'a'],
    ], [
        'a' => 1,
    ]);

    $wrongArity = $evaluator->evaluate([
        'subtract' => [10, 5, 1],
    ], []);

    $nonNumeric = $evaluator->evaluate([
        'add' => [1, 'foo'],
    ], []);

    $emptyMax = $evaluator->evaluate([
        'max' => [],
    ], []);

    $emptyMin = $evaluator->evaluate([
        'min' => [],
    ], []);

    expect($notAList->succeeded)->toBeFalse()
        ->and($notAList->errorCode)->toBe(FormulaErrorCode::InvalidOperand)
        ->and($wrongArity->succeeded)->toBeFalse()
        ->and($wrongArity->errorCode)->toBe(FormulaErrorCode::InvalidOperand)
        ->and($nonNumeric->succeeded)->toBeFalse()
        ->and($nonNumeric->errorCode)->toBe(FormulaErrorCode::InvalidOperand)
        ->and($emptyMax->succeeded)->toBeFalse()
        ->and($emptyMax->errorCode)->toBe(FormulaErrorCode::InvalidOperand)
        ->and($emptyMin->succeeded)->toBeFalse()
        ->and($emptyMin->errorCode)->toBe(FormulaErrorCode::InvalidOperand);
});

it('rejects invalid boolean and conditional operator inputs', function (): void {
    $evaluator = new FormulaEvaluator();

    $notBoolean = $evaluator->evaluate([
        'not' => 5,
    ], []);

    $badIfShape = $evaluator->evaluate([
        'if' => [true, 1],
    ], []);

    $badIfCondition = $evaluator->evaluate([
        'if' => [1, 2, 3],
    ], []);

    $badAndOperand = $evaluator->evaluate([
        'and' => [true, 1],
    ], []);

    expect($notBoolean->succeeded)->toBeFalse()
        ->and($notBoolean->errorCode)->toBe(FormulaErrorCode::InvalidOperand)
        ->and($badIfShape->succeeded)->toBeFalse()
        ->and($badIfShape->errorCode)->toBe(FormulaErrorCode::InvalidOperand)
        ->and($badIfCondition->succeeded)->toBeFalse()
        ->and($badIfCondition->errorCode)->toBe(FormulaErrorCode::InvalidOperand)
        ->and($badAndOperand->succeeded)->toBeFalse()
        ->and($badAndOperand->errorCode)->toBe(FormulaErrorCode::InvalidOperand);
});

it('rejects invalid comparison operator inputs', function (): void {
    $evaluator = new FormulaEvaluator();

    $badPair = $evaluator->evaluate([
        'equals' => [1],
    ], []);

    $badNumericComparison = $evaluator->evaluate([
        'greaterThan' => [1, 'foo'],
    ], []);

    expect($badPair->succeeded)->toBeFalse()
        ->and($badPair->errorCode)->toBe(FormulaErrorCode::InvalidOperand)
        ->and($badNumericComparison->succeeded)->toBeFalse()
        ->and($badNumericComparison->errorCode)->toBe(FormulaErrorCode::InvalidOperand);
});
